<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageSearchResource extends JsonResource
{
	/**
	 * Transform the resource into an array.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(Request $request): array
	{
		return [
			"id" => $this->id,
			"sender_id" => $this->sender_id,
			"type" => $this->type,
			"body" => $this->_highlighted["body"] ?? $this->body,
			"reply_to_id" => $this->reply_to_id,
			"edited_at" => $this->edited_at,
			"created_at" => $this->created_at,
			"_score" => $this->_score ?? null,
		];
	}
}
