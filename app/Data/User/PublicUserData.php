<?php

namespace App\Data\User;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class PublicUserData extends Data
{
	public function __construct(
		public int $id,
		public string $username,
		public ?string $bio,
		public string $email,
		public CarbonImmutable $last_seen_at,
	) {
	}
}
