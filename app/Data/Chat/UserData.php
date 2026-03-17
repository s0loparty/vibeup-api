<?php

namespace App\Data\Chat;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class UserData extends Data
{
	public function __construct(
		public int $id,
		public string $name,
		public string $email,
		public CarbonImmutable $last_seen_at,
	) {
	}
}
