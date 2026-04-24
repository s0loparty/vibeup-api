<?php

namespace App\Http\Resources\Chat;

use App\Http\Resources\Message\MessageResource;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatResource extends JsonResource
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
			// 'title' => $this->title,
			'display_title' => $this->display_title,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
			// 'creator' => $this->whenLoaded('creator', fn () => UserResource::make($this->creator)),
			// 'last_message' => $this->whenLoaded('lastMessage', fn () => MessageResource::make($this->lastMessage)),
			// 'members' => $this->whenLoaded('members', fn () => UserResource::collection($this->members)),
			'members' => UserResource::collection($this->whenLoaded('members')),
			'creator' => new UserResource($this->whenLoaded('creator')),
			'last_message' => new MessageResource($this->whenLoaded('lastMessage')),
		];
	}
}
