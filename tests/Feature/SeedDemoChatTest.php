<?php

namespace Tests\Feature;

use App\Enums\ChatType;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class SeedDemoChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_demo_users_and_chats_for_the_selected_user_only_once(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $originalPassword = $user->password;

        $this->artisan('demo:seed-chat', ['user' => $user->id])->assertSuccessful();

        $this->assertSame(52, User::query()->count());
        $this->assertSame(50, $user->chats()->count());
        $this->assertSame(0, $otherUser->chats()->count());
        $this->assertSame(50, Chat::query()->count());
        $this->assertGreaterThanOrEqual(500, Message::query()->count());
        $this->assertLessThanOrEqual(5000, Message::query()->count());
        $this->assertSame($originalPassword, $user->fresh()->password);

        foreach (User::query()->whereNotIn('id', [$user->id, $otherUser->id])->get() as $demoUser) {
            $this->assertStringEndsWith('@example.invalid', $demoUser->email);
            $this->assertFalse(Hash::check('password', $demoUser->password));
        }

        $privatePairs = [];

        foreach ($user->chats as $chat) {
            $members = $chat->members()->pluck('users.id')->all();
            $senders = $chat->messages()->distinct()->pluck('sender_id')->all();
            $this->assertContains($user->id, $members);
            $this->assertEqualsCanonicalizing($members, $senders);
            $this->assertCount(count(array_unique($members)), $members);
            $this->assertGreaterThanOrEqual(10, $chat->messages()->count());
            $this->assertLessThanOrEqual(100, $chat->messages()->count());
            $this->assertSame($chat->messages()->latest('id')->first()->id, $chat->last_message_id);

            if ($chat->type === ChatType::PRIVATE) {
                $this->assertCount(2, $members);
                $other = array_values(array_diff($members, [$user->id]))[0];
                $this->assertNotContains($other, $privatePairs);
                $privatePairs[] = $other;
            } else {
                $this->assertGreaterThanOrEqual(3, count($members));
                $this->assertLessThanOrEqual(8, count($members));
            }
        }

        $messageCount = Message::query()->count();
        $user->createdChats()->firstOrFail()->update(['title' => 'Переименованный демо-чат']);
        $this->artisan('demo:seed-chat', ['user' => $user->id])->assertSuccessful();
        $this->assertSame(52, User::query()->count());
        $this->assertSame(50, Chat::query()->count());
        $this->assertSame($messageCount, Message::query()->count());
    }

    public function test_it_rolls_back_all_demo_data_if_creating_a_message_fails(): void
    {
        $user = User::factory()->create();
        Event::listen('eloquent.creating: '.Message::class, fn () => throw new RuntimeException('Test failure'));

        try {
            $this->artisan('demo:seed-chat', ['user' => $user->id])->run();
            $this->fail('The command should propagate the failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Test failure', $exception->getMessage());
        }

        $this->assertSame(1, User::query()->count());
        $this->assertSame(0, Chat::query()->count());
        $this->assertSame(0, Message::query()->count());
    }

    public function test_it_refuses_missing_users_without_creating_data(): void
    {
        $this->artisan('demo:seed-chat', ['user' => 999999])->assertFailed();
        $this->assertSame(0, Chat::query()->count());
        $this->assertSame(0, User::query()->count());
    }

    public function test_it_does_not_modify_an_existing_user_with_a_reserved_demo_email(): void
    {
        $user = User::factory()->create();
        $existing = User::factory()->create(['email' => 'demo-vibe-'.$user->id.'-bulk-1@example.invalid']);
        $originalPassword = $existing->password;

        $this->artisan('demo:seed-chat', ['user' => $user->id])->assertSuccessful();

        $this->assertSame(2, User::query()->count());
        $this->assertSame(0, Chat::query()->count());
        $this->assertSame($originalPassword, $existing->fresh()->password);
    }

    public function test_it_refuses_a_soft_deleted_user(): void
    {
        $user = User::factory()->create();
        User::query()->whereKey($user->id)->update(['deleted_at' => now()]);

        $this->artisan('demo:seed-chat', ['user' => $user->id])->assertFailed();
        $this->assertSame(0, Chat::query()->count());
        $this->assertSame(1, User::query()->count());
    }

    public function test_it_requires_explicit_force_in_production(): void
    {
        $user = User::factory()->create();
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('demo:seed-chat', ['user' => $user->id])->assertFailed();
        $this->assertSame(0, Chat::query()->count());
        $this->assertSame(1, User::query()->count());

        $this->artisan('demo:seed-chat', ['user' => $user->id, '--force' => true])->assertSuccessful();
        $this->assertSame(50, Chat::query()->count());
    }
}
