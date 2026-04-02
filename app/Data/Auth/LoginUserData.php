<?php

namespace App\Data\Auth;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class LoginUserData extends Data
{
    public function __construct(
        #[Required, Email]
        public string $email,
        #[Required]
        public string $password,
    ) {}
}
