<?php

namespace App\Data\Chat;

use App\Enums\ChatType;
use Spatie\LaravelData\Data;

class CreateChatData extends Data
{
	public function __construct(
		public ChatType $type,
		public ?string $title = null,
		public ?int $participant_id = null,
	) {
	}
}
