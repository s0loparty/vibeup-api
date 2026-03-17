<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class LoginUserAction
{
	public function execute(string $email, string $password): User
	{
		$user = User::where('email', $email)->first();

		if (! $user || ! Hash::check($password, $user->password)) {
			throw ValidationException::withMessages([
				'email' => ['Invalid credentials'],
			]);
		}

		return $user;
	}
}
