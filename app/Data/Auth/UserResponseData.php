<?php

namespace App\Data\Auth;

use App\Models\User;
use Spatie\LaravelData\Data;

class UserResponseData extends Data
{
	public function __construct(
		public int $id,
		public string $username,
		public ?string $bio,
		public string $email,
		public ?string $email_verified_at = null,
		public ?string $last_seen_at = null,
		public string $created_at,
		public string $updated_at,
	) {
	}

	public static function fromModel(User $user): self
	{
		return new self(
			id: $user->id,
			username: $user->username,
			bio: $user->bio,
			email: $user->email,
			email_verified_at: $user->email_verified_at?->toISOString(),
			last_seen_at: $user->last_seen_at?->toISOString(),
			created_at: $user->created_at->toISOString(),
			updated_at: $user->updated_at->toISOString(),
		);
	}
}
