<?php

namespace App\Services;

use App\Actions\Chat\CreateChatAction;
use App\Actions\Chat\DeleteChatAction;
use App\Actions\Chat\UpdateChatAction;
use App\Actions\Chat\FindExistingPrivateChatAction;
use App\Data\Chat\CreateChatData;
use App\Data\Chat\UpdateChatData;
use App\Models\Chat;
use App\Models\User;
use App\Enums\ChatType;

final class ChatService
{
	public function __construct(
		private CreateChatAction $createChatAction,
		private UpdateChatAction $updateChatAction,
		private DeleteChatAction $deleteChatAction,
		private FindExistingPrivateChatAction $findExistingPrivateChatAction,
	) {}

	/**
	 * Создать чат
	 */
	public function createChat(User $user, CreateChatData $data): array
	{
		// Валидация для разных типов чатов
		if ($data->type === ChatType::PRIVATE && !$data->participant_id) {
			return [
				'success' => false,
				'error' => 'Для приватного чата необходимо указать participant_id',
				'status' => 400,
			];
		}

		if ($data->type === ChatType::GROUP && empty($data->title)) {
			return [
				'success' => false,
				'error' => 'Для группового чата требуется название',
				'status' => 400,
			];
		}

		// Проверка существования участника для private чата
		if ($data->type === ChatType::PRIVATE) {
			$participant = User::find($data->participant_id);
			if (!$participant) {
				return [
					'success' => false,
					'error' => 'Пользователь не найден',
					'status' => 404,
				];
			}

			// Проверяем, не существует ли уже чат с этим пользователем
			$existingChat = $this->findExistingPrivateChatAction->execute($user, $data->participant_id);
			if ($existingChat) {
				return [
					'success' => true,
					'exists' => true,
					'chat' => $existingChat->load('members'),
					'status' => 200,
				];
			}
		}

		$result = $this->createChatAction->execute($user, $data);

		return [
			'success' => true,
			'exists' => false,
			'chat' => $result['chat'],
			'status' => 201,
		];
	}

	/**
	 * Обновить чат
	 */
	public function updateChat(Chat $chat, User $user, UpdateChatData $data): array
	{
		try {
			$result = $this->updateChatAction->execute($chat, $user, $data);

			return [
				'success' => true,
				'chat' => $result['chat'],
				'status' => 200,
			];
		} catch (\Exception $e) {
			$statusCode = match ($e->getMessage()) {
				'Not a member of this chat' => 403,
				'Only owner or moderator can update chat' => 403,
				'Cannot change title of private chat' => 400,
				default => 500,
			};

			return [
				'success' => false,
				'error' => $e->getMessage(),
				'status' => $statusCode,
			];
		}
	}

	/**
	 * Удалить чат
	 */
	public function deleteChat(Chat $chat, User $user): array
	{
		try {
			$result = $this->deleteChatAction->execute($chat, $user);

			return [
				'success' => true,
				'message' => $result['message'],
				'status' => 200,
			];
		} catch (\Exception $e) {
			return [
				'success' => false,
				'error' => $e->getMessage(),
				'status' => 403,
			];
		}
	}

	/**
	 * Найти существующий приватный чат
	 */
	public function findExistingPrivateChat(User $user, int $participantId): ?Chat
	{
		return $this->findExistingPrivateChatAction->execute($user, $participantId);
	}
}
