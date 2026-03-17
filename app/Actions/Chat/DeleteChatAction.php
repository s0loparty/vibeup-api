<?php

namespace App\Actions\Chat;

use App\Models\Chat;
use App\Models\User;
use App\Enums\ChatMemberRole;

final class DeleteChatAction
{
	public function execute(Chat $chat, User $user): array
	{
		// Проверка: только владелец может удалить чат
		$member = $chat->members()->where('user_id', $user->id)->first();
		if (!$member || $member->pivot->role !== ChatMemberRole::OWNER) {
			throw new \Exception('Only owner can delete this chat');
		}

		$chat->delete();

		return [
			'success' => true,
			'message' => 'Чат успешно удалён',
		];
	}
}
