<?php

namespace App\Data\Chat;

use App\Enums\ChatType;
use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CreateChatData extends Data
{
    public function __construct(
        #[Required, Enum(ChatType::class)]
        public ChatType $type,
        public ?string $title = null,
        public ?int $participant_id = null,
    ) {}
}
