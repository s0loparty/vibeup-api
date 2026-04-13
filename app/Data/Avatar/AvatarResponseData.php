<?php

namespace App\Data\Avatar;

use Spatie\LaravelData\Data;

class AvatarResponseData extends Data
{
    public function __construct(
        public ?string $original = null,
        public ?string $thumbnail = null,
    ) {}
}
