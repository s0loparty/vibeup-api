<?php

namespace App\Actions\Chat;

use App\Data\Chat\UpdateChatData;
use App\Models\Chat;
use App\Models\User;
use App\Enums\ChatType;
use App\Enums\ChatMemberRole;

final class UpdateChatAction
{
	public function execute(Chat $chat, User $user, UpdateChatData $data): array
	{
		// Проверка: пользователь является участником чата
		$member = $chat->members()->where('user_id', $user->id)->first();
		if (!$member) {
			throw new \Exception('Not a member of this chat');
		}

		// Проверка: только владелец или модератор может редактировать
		if (!in_array($member->pivot->role, [ChatMemberRole::OWNER, ChatMemberRole::MODERATOR])) {
			throw new \Exception('Only owner or moderator can update chat');
		}

		// Для private чата нельзя изменить title
		if ($chat->type === ChatType::PRIVATE && $data->title !== null) {
			throw new \Exception('Cannot change title of private chat');
		}

		$chat->update($data->toArray());

		return [
			'success' => true,
			'chat' => $chat->fresh(),
		];
	}
}
