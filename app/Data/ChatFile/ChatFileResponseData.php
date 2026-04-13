<?php

namespace App\Data\ChatFile;

use Spatie\LaravelData\Data;

class ChatFileResponseData extends Data
{
    public function __construct(
        public int $id,
        public string $original_name,
        public string $mime_type,
        public int $size,
        public ?string $temporary_url,
        public string $created_at,
    ) {}
}
