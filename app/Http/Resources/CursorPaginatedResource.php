<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\CursorPaginator;

class CursorPaginatedResource extends JsonResource
{
    public function __construct(
        CursorPaginator $resource,
        private readonly array $data = []
    ) {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        /** @var CursorPaginator $paginator */
        $paginator = $this->resource;

        return [
            'data' => $this->data['data'] ?? $paginator->items(),
            'meta' => [
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'prev_page_url' => $paginator->previousPageUrl(),
            ],
        ];
    }
}
