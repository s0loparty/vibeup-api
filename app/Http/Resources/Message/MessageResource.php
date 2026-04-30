<?php

namespace App\Http\Resources\Message;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
	/**
	 * Transform the resource into an array.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(Request $request): array
	{
		return [
			'id' => $this->id,
			'type' => $this->type,
			'body' => $this->body,
			'updated_at' => $this->updated_at,
			'created_at' => $this->created_at,
			'reply_to' => new PublicMessageResource($this->whenLoaded('reply_to')),
			'sender' => new PublicMessageResource($this->whenLoaded('sender')),
		];
	}
}
