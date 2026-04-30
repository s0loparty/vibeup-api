<?php

namespace App\Http\Resources\Chat;

use App\Http\Resources\Message\MessageResource;
use App\Http\Resources\Message\PublicMessageResource;
use App\Http\Resources\User\PublicUserResource;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicChatResource extends JsonResource
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
			'display_title' => $this->display_title,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
			'created_by' => $this->created_by,
			'members' => PublicUserResource::collection($this->whenLoaded('members')),
			'creator' => new UserResource($this->whenLoaded('creator')),
			'last_message' => new PublicMessageResource($this->whenLoaded('lastMessage')),
		];
	}
}
