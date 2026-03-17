<?php

namespace App\Data\Message;

use App\Enums\MessageType;
use Spatie\LaravelData\Data;

class UpdateMessageData extends Data
{
	public function __construct(
		public ?MessageType $type = null,
		public ?string $body = null,
	) {
	}
}
