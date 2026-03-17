<?php

namespace App\Data\Message;

use App\Enums\MessageType;
use Spatie\LaravelData\Data;

class CreateMessageData extends Data
{
	public function __construct(
		public MessageType $type,
		public ?string $body,
		public ?int $reply_to_id = null,
	) {
	}
}
