<?php

namespace App\Data\Chat;

use App\Enums\ChatType;
use App\Models\Message;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

class ChatData extends Data
{
	public function __construct(
		public int $id,

		public ChatType $type,

		public ?string $title = null,

		public UserData $created_by,

		public CarbonImmutable $created_at,

		public ?int $last_message_id = null,

		public ?Message $last_message = null,
	) {
	}
}
