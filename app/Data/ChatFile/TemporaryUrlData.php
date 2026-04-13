<?php

namespace App\Data\ChatFile;

use Spatie\LaravelData\Data;

class TemporaryUrlData extends Data
{
    public function __construct(
        public string $url,
        public string $expires_at,
    ) {}
}
