<?php

namespace App\Http\Resources\Message;

use App\Http\Resources\User\PublicUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicMessageResource extends JsonResource
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
			'chat_id' => $this->chat_id,
			'sender_id' => $this->sender_id,
			'type' => $this->type,
			'body' => $this->body,
			'reply_to_id' => $this->reply_to_id,
			'edited_at' => $this->edited_at?->toISOString(),
			'created_at' => $this->created_at?->toISOString(),
			'updated_at' => $this->updated_at?->toISOString(),
			'sender' => new PublicUserResource($this->whenLoaded('sender')),
			'reply_to' => new PublicMessageResource($this->whenLoaded('replyTo')),
		];
	}
}
