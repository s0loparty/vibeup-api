<?php

namespace App\Data\Chat;

use Spatie\LaravelData\Data;

class UpdateChatData extends Data
{
    public function __construct(
        public ?string $title = null,
    ) {}
}
