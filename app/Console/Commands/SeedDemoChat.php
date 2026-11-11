<?php

namespace App\Console\Commands;

use App\Enums\ChatMemberRole;
use App\Enums\ChatType;
use App\Enums\MessageType;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SeedDemoChat extends Command
{
    private const DEMO_CHAT_TITLE = 'Демо: знакомство с VibeUp';

    protected $signature = 'demo:seed-chat {user : ID существующего пользователя} {--force : Разрешить запуск в production}';

    protected $description = 'Создать демонстрационных пользователей и чаты для выбранного пользователя';

    public function handle(): int
    {
        if ($this->laravel->environment('production') && ! $this->option('force')) {
            $this->error('Для запуска в production явно укажите --force.');

            return self::FAILURE;
        }

        $userId = (string) $this->argument('user');

        if (! ctype_digit($userId) || (int) $userId < 1) {
            $this->error('Укажите положительный числовой ID пользователя.');

            return self::FAILURE;
        }

        $result = (new User)->getConnection()->transaction(function () use ($userId): string {
            $owner = User::query()->whereKey($userId)->whereNull('deleted_at')->lockForUpdate()->first();

            if (! $owner) {
                return 'missing';
            }

            $demoEmails = collect(range(1, 3))->map(
                fn (int $number): string => 'demo-vibe-'.$owner->id.'-'.$number.'@example.invalid'
            );

            if ($owner->createdChats()->where('type', ChatType::GROUP->value)->where('title', self::DEMO_CHAT_TITLE)->exists()
                || User::query()->whereIn('email', $demoEmails)->exists()) {
                return 'exists';
            }

            $demoUsers = collect(['Демо: Анна', 'Демо: Борис', 'Демо: Виктория'])->map(
                fn (string $name, int $index): User => User::withoutEvents(fn (): User => User::query()->create([
                    'username' => $name,
                    'email' => $demoEmails[$index],
                    'password' => Hash::make(Str::random(64)),
                ]))
            );

            $group = $owner->createdChats()->create([
                'type' => ChatType::GROUP,
                'title' => self::DEMO_CHAT_TITLE,
            ]);
            $group->members()->attach($owner->id, ['role' => ChatMemberRole::OWNER->value]);
            foreach ($demoUsers as $demoUser) {
                $group->members()->attach($demoUser->id, ['role' => ChatMemberRole::MEMBER->value]);
            }
            $this->addMessages($group, [
                [$demoUsers[0], 'Привет! Это демонстрационный групповой чат.'],
                [$demoUsers[1], 'Здесь можно попробовать переписку.'],
                [$demoUsers[2], 'Демонстрационные данные можно удалить после проверки.'],
            ]);

            foreach ($demoUsers->take(2) as $demoUser) {
                $chat = $owner->createdChats()->create(['type' => ChatType::PRIVATE]);
                $chat->members()->attach($owner->id, ['role' => ChatMemberRole::MEMBER->value]);
                $chat->members()->attach($demoUser->id, ['role' => ChatMemberRole::MEMBER->value]);
                $this->addMessages($chat, [
                    [$demoUser, 'Привет! Это пример личного сообщения.'],
                    [$demoUser, 'Это второе демонстрационное сообщение.'],
                ]);
            }

            return 'created';
        });

        if ($result === 'missing') {
            $this->error('Пользователь не найден.');

            return self::FAILURE;
        }

        $this->info($result === 'exists' ? 'Демонстрационные данные или зарезервированные адреса уже существуют; изменений нет.' : 'Демонстрационные пользователи и чаты созданы.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{0: User, 1: string}>  $messages
     */
    private function addMessages(Chat $chat, array $messages): void
    {
        foreach ($messages as [$sender, $body]) {
            $message = $chat->messages()->create([
                'sender_id' => $sender->id,
                'type' => MessageType::TEXT,
                'body' => $body,
            ]);
        }

        $chat->update(['last_message_id' => $message->id]);
    }
}
