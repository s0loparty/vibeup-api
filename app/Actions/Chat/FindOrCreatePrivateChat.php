<?php

namespace App\Actions\Chat;

use App\Data\User\PublicUserData;
use App\Enums\ChatMemberRole;
use App\Enums\ChatType;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class FindOrCreatePrivateChat
{
	public function execute(PublicUserData $currentUser, PublicUserData $otherUser): Chat
	{
		$chat = Chat::query()->where('type', '=', ChatType::PRIVATE)
			->whereHas('members', fn ($q) => $q->where('user_id', $currentUser->id))
			->whereHas('members', fn ($q) => $q->where('user_id', $otherUser->id))
			->first();

		if ($chat) {
			return $chat;
		}

		return DB::transaction(function () use ($currentUser, $otherUser) {
			$chat = Chat::create(['type' => ChatType::PRIVATE , 'created_by' => $currentUser->id]);

			try {
				$chat->members()->attach([
					$currentUser->id => ['role' => ChatMemberRole::OWNER],
					$otherUser->id => ['role' => ChatMemberRole::MEMBER],
				]);
			} catch (QueryException $e) {
				// Race condition: другой процесс уже создал чат
				if ($this->isUniqueConstraintViolation($e)) {
					return $this->execute($currentUser, $otherUser);
				}

				throw $e;
			}
		});
	}

	private function isUniqueConstraintViolation(QueryException $e): bool
	{
		return str_contains($e->getMessage(), 'chat_members_chat_id_user_id_unique');
	}
}
