<?php

namespace App\Data\Auth;

use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Data;

class RegisterUserData extends Data
{
    public function __construct(
        #[Unique(table: 'users', column: 'email')]
        public string $email,
        #[Min(8)]
        public string $password,
    ) {}
}
