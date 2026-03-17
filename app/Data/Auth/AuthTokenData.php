<?php

namespace App\Data\Auth;

use Spatie\LaravelData\Data;

final class AuthTokenData extends Data
{
	public function __construct(
		public string $token,
		public string $type = 'Bearer',
	) {
	}
}
