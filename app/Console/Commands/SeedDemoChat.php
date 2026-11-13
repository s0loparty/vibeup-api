<?php

namespace App\Console\Commands;

use App\Enums\ChatMemberRole;
use App\Enums\ChatType;
use App\Enums\MessageType;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SeedDemoChat extends Command
{
    private const CHAT_COUNT = 50;
    private const DEMO_USER_COUNT = 50;
    private const GROUP_TITLES = [
        'Демо: Общение', 'Демо: Команда', 'Демо: Друзья',
        'Демо: Обсуждение', 'Демо: Планы', 'Демо: Встреча',
    ];
    private const MESSAGE_BODIES = [
        'Привет! Как дела?', 'Есть новости?', 'Отличная идея!',
        'Давай обсудим подробнее.', 'Хорошо, договорились.',
        'Спасибо за информацию!', 'Что думаете по этому поводу?',
        'Мне подходит.', 'Напишу чуть позже.', 'Супер, спасибо!',
        'Когда тебе удобно?', 'Давайте сделаем именно так.',
        'Звучит интересно!', 'Я тоже так считаю.',
    ];

    protected $signature = 'demo:seed-chat {user : ID существующего пользователя} {--force : Разрешить запуск в production}';

    protected $description = 'Создать 50 демонстрационных чатов со случайными сообщениями для выбранного пользователя';

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

            // Отдельный namespace не конфликтует с тремя пользователями старого демо-сидера.
            $demoEmails = collect(range(1, self::DEMO_USER_COUNT))->map(
                fn (int $number): string => 'demo-vibe-'.$owner->id.'-bulk-'.$number.'@example.invalid'
            );

            // Не трогаем чужие аккаунты при конфликте и не создаём повторный набор.
            if (User::query()->whereIn('email', $demoEmails)->exists()) {
                return 'exists';
            }

            $names = [
                'Анна', 'Борис', 'Виктория', 'Дмитрий', 'Елена',
                'Игорь', 'Мария', 'Никита', 'Ольга', 'Павел',
                'София', 'Артём', 'Алиса', 'Максим', 'Дарья',
            ];

            $demoUsers = $demoEmails->map(
                fn (string $email, int $index): User => User::withoutEvents(fn (): User => User::query()->create([
                    'username' => 'Демо: '.$names[$index % count($names)].' '.($index + 1),
                    'email' => $email,
                    'password' => Hash::make(Str::random(64)),
                ]))
            );

            // У каждого личного чата свой собеседник — без дублирующихся пар.
            $privateUsers = $demoUsers->shuffle()->values();
            $privateIndex = 0;

            for ($number = 1; $number <= self::CHAT_COUNT; $number++) {
                $isGroup = (bool) random_int(0, 1);

                if ($isGroup) {
                    // В группе от 3 до 8 участников, включая владельца.
                    $participants = collect([$owner])->concat(
                        $demoUsers->random(random_int(2, 7))
                    )->values();

                    $chat = $owner->createdChats()->create([
                        'type' => ChatType::GROUP,
                        'title' => self::GROUP_TITLES[array_rand(self::GROUP_TITLES)].' #'.$number,
                    ]);
                } else {
                    $participants = collect([$owner, $privateUsers[$privateIndex++]]);
                    $chat = $owner->createdChats()->create(['type' => ChatType::PRIVATE]);
                }

                foreach ($participants as $participant) {
                    $chat->members()->attach($participant->id, [
                        'role' => $isGroup && $participant->id === $owner->id
                            ? ChatMemberRole::OWNER->value
                            : ChatMemberRole::MEMBER->value,
                    ]);
                }

                $this->addMessages($chat, $participants, random_int(10, 100));
            }

            return 'created';
        });

        if ($result === 'missing') {
            $this->error('Пользователь не найден.');

            return self::FAILURE;
        }

        $this->info($result === 'exists'
            ? 'Новый набор демо-данных уже существует или его зарезервированные адреса заняты; изменений нет.'
            : 'Созданы 50 демо-чатов, 50 пользователей и по 10–100 сообщений в каждом чате.');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, User>  $participants
     */
    private function addMessages(Chat $chat, Collection $participants, int $count): void
    {
        // Сначала по сообщению от каждого участника, затем случайные отправители.
        // Поскольку участников не больше восьми, минимум в 10 сообщений выполняется.
        $senders = $participants->shuffle()->values()->all();

        while (count($senders) < $count) {
            $senders[] = $participants->random();
        }

        shuffle($senders);

        foreach ($senders as $sender) {
            $message = $chat->messages()->create([
                'sender_id' => $sender->id,
                'type' => MessageType::TEXT,
                'body' => self::MESSAGE_BODIES[array_rand(self::MESSAGE_BODIES)],
            ]);
        }

        $chat->update(['last_message_id' => $message->id]);
    }
}
