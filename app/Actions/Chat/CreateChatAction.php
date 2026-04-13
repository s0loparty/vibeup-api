<?php

namespace App\Actions\Chat;

use App\Data\Chat\CreateChatData;
use App\Enums\ChatMemberRole;
use App\Enums\ChatType;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateChatAction
{
	public function execute(User $user, CreateChatData $data): array
	{
		return DB::transaction(function () use ($user, $data) {
			// Для приватного чата сохраняем ID собеседника

			// Создаём чат
			$chat = Chat::create([
				'type' => $data->type,
				'title' => $data->title,
				'created_by' => $user->id,
			]);

			if ($data->type === ChatType::PRIVATE) {
				// Добавляем участников
				$chat->members()->attach([
					$user->id => ['role' => ChatMemberRole::OWNER],
					$data->participant_id => ['role' => ChatMemberRole::MEMBER],
				]);
			} else {
				// Для группового чата создатель становится владельцем
				$chat->members()->attach([
					$user->id => ['role' => ChatMemberRole::OWNER],
				]);
			}

			return [
				'success' => true,
				'chat' => $chat->fresh(['members']),
			];
		});
	}
}
