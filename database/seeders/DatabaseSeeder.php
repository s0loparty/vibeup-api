<?php

namespace Database\Seeders;

use App\Enums\ChatMemberRole;
use App\Enums\ChatType;
use App\Models\Chat;
use App\Models\ChatMember;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('Seeding database...');

        // 1. Create a main user and 9 other users
        $mainUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $otherUsers = User::factory(9)->create();
        $this->command->info('Users created.');

        // 2. Create a Group chat with 5 members
        $groupChat = Chat::factory()->create([
            'title' => 'Laravel Developers',
            'created_by' => $mainUser->id,
        ]);

        $groupMembers = collect([$mainUser])->merge($otherUsers->slice(0, 4));
        $groupMembers->each(fn (User $user) => ChatMember::factory()->create([
            'chat_id' => $groupChat->id,
            'user_id' => $user->id,
            'role' => $user->id === $mainUser->id ? ChatMemberRole::OWNER : ChatMemberRole::MEMBER,
        ]));

        // Create 30 messages in the group chat
        for ($i = 0; $i < 30; $i++) {
            Message::factory()->create([
                'chat_id' => $groupChat->id,
                'sender_id' => $groupMembers->random()->id,
            ]);
        }
        $this->command->info('Group chat with messages created.');

        // 3. Create a Private chat between the main user and another user
        $privateChat = Chat::factory()->private()->create([
            'created_by' => $mainUser->id,
        ]);
        $privateChatMembers = collect([$mainUser, $otherUsers->get(5)]);

        $privateChatMembers->each(fn (User $user) => ChatMember::factory()->create([
            'chat_id' => $privateChat->id,
            'user_id' => $user->id,
            'role' => ChatMemberRole::MEMBER,
        ]));

        // Create 20 messages in the private chat
        for ($i = 0; $i < 20; $i++) {
            Message::factory()->create([
                'chat_id' => $privateChat->id,
                'sender_id' => $privateChatMembers->random()->id,
            ]);
        }
        $this->command->info('Private chat with messages created.');

        // 4. Update last message for each chat
        $groupChat->update(['last_message_id' => $groupChat->messages()->latest()->first()->id]);
        $privateChat->update(['last_message_id' => $privateChat->messages()->latest()->first()->id]);
        $this->command->info('Last message IDs updated.');

        $this->command->info('Database seeding completed successfully!');
    }
}
