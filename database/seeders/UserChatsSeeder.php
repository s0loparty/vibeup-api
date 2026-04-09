<?php

namespace Database\Seeders;

use App\Enums\ChatMemberRole;
use App\Enums\ChatType;
use App\Enums\MessageType;
use App\Models\Chat;
use App\Models\ChatMember;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserChatsSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		if (app()->environment('production')) {
			$this->command->error('This seeder is not intended for production environments.');
			return;
		}

		$chatsPerUser    = 60;
		$messagesPerChat = 100;

		$this->command->warn("This seeder will create {$chatsPerUser} chats for EACH user, with {$messagesPerChat} messages in each chat.");
		$this->command->warn('This will generate a large amount of data and may take some time.');

		// if (! $this->command->confirm('Do you wish to continue?', false)) {
		//     $this->command->info('Seeding cancelled.');
		//     return;
		// }

		$users = User::all();
		if ($users->isEmpty()) {
			$this->command->info('No users found. Creating 5 new users first.');
			$users = User::factory()->count(5)->create();
		}

		$this->command->info("Starting to seed for {$users->count()} users.");
		$totalChats    = $users->count() * $chatsPerUser;
		$totalMessages = $totalChats * $messagesPerChat;
		$this->command->info("Will create {$totalChats} chats and {$totalMessages} messages.");

		$progressBar = $this->command->getOutput()->createProgressBar($users->count());
		$progressBar->start();

		// Получаем все типы сообщений
		$messageTypes = MessageType::cases();

		foreach ($users as $user) {
			// Создаем чаты по одному, чтобы получить правильные ID (PostgreSQL)
			$chatIds = [];
			for ($i = 0; $i < $chatsPerUser; $i++) {
				$chatId = DB::table('chats')->insertGetId([
					'type' => [ChatType::PRIVATE, ChatType::GROUP][array_rand([ChatType::PRIVATE, ChatType::GROUP])],
					'title' => fake()->sentence(3),
					'created_by' => $user->id,
					'created_at' => now(),
					'updated_at' => now(),
				]);
				$chatIds[] = $chatId;
			}

			// Для каждого чата создаем участников и сообщения
			foreach ($chatIds as $chatId) {
				// Участники чата
				$memberData = [
					[
						'chat_id' => $chatId,
						'user_id' => $user->id,
						'role' => ChatMemberRole::OWNER,
						'created_at' => now(),
						'updated_at' => now(),
					],
				];

				// Добавляем 1-3 случайных участников
				$otherMembers = User::where('id', '!=', $user->id)
					->inRandomOrder()
					->limit(rand(1, 3))
					->get();

				foreach ($otherMembers as $member) {
					$memberData[] = [
						'chat_id' => $chatId,
						'user_id' => $member->id,
						'role' => ChatMemberRole::MEMBER,
						'created_at' => now(),
						'updated_at' => now(),
					];
				}

				ChatMember::insert($memberData);

				// Собираем ID всех участников для сообщений
				$allMemberIds = array_column($memberData, 'user_id');

				// Создаем сообщения чанками по 500
				$messageData = [];
				$baseTime    = now()->subDays(rand(0, 30));

				for ($m = 0; $m < $messagesPerChat; $m++) {
					$messageData[] = [
						'chat_id' => $chatId,
						'sender_id' => $allMemberIds[array_rand($allMemberIds)],
						'type' => $messageTypes[array_rand($messageTypes)],
						'body' => fake()->sentence(rand(3, 15)),
						'reply_to_id' => null,
						'edited_at' => null,
						'created_at' => $baseTime->copy()->addSeconds($m * rand(10, 300)),
						'updated_at' => now(),
					];

					// Вставляем чанками
					if (count($messageData) >= 500) {
						Message::insert($messageData);
						$messageData = [];
					}
				}

				// Вставляем оставшиеся сообщения
				if (! empty($messageData)) {
					Message::insert($messageData);
				}

				// Обновляем last_message_id для чата
				$lastMessageId = DB::table('messages')
					->where('chat_id', $chatId)
					->max('id');

				DB::table('chats')
					->where('id', $chatId)
					->update(['last_message_id' => $lastMessageId]);
			}

			$progressBar->advance();
		}

		$progressBar->finish();
		$this->command->newLine(2);
		$this->command->info("Successfully seeded {$totalChats} chats and {$totalMessages} messages for {$users->count()} users.");
	}
}
