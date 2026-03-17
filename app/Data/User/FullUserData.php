<?php

namespace App\Data\User;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class FullUserData extends Data
{
	public function __construct(
		public int $id,
		public string $name,
		public string $email,
		public ?string $remember_token,
		public ?CarbonImmutable $email_verified_at,
		public ?CarbonImmutable $last_seen_at,
		public ?CarbonImmutable $deleted_at,
		public ?CarbonImmutable $created_at,
		public ?CarbonImmutable $updated_at,
	) {
	}
}
