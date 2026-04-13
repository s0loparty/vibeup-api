<?php

namespace App\Data\Chat;

use App\Data\User\PublicUserData;
use App\Enums\ChatType;
use App\Models\Message;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class ChatData extends Data
{
	public function __construct(
		public int $id,
		public ChatType $type,
		public ?string $title,
		public string $display_title,
		public int $created_by,
		public ?int $last_message_id,
		public ?Message $last_message,
		public CarbonImmutable $created_at,
		public CarbonImmutable $updated_at,
	) {
	}
}
