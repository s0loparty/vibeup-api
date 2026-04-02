<?php

namespace App\Data\Message;

use App\Enums\MessageType;
use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\RequiredIf;
use Spatie\LaravelData\Data;

class CreateMessageData extends Data
{
    public function __construct(
        #[Required, Enum(MessageType::class)]
        public MessageType $type,
        #[RequiredIf('type', MessageType::TEXT)]
        public ?string $body = null,
        public ?int $reply_to_id = null,
    ) {}
}
