<?php

namespace App\Actions\Auth;

use App\Data\Auth\RegisterUserData;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class RegisterUserAction
{
	public function execute(RegisterUserData $data): User
	{
		return User::create([
			'email' => $data->email,
			'password' => Hash::make($data->password),
		]);
	}
}
