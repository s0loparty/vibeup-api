<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Chat\ChatData;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Data\Chat\CreateChatData;
use App\Data\Chat\ShowChatData;
use App\Data\Chat\UpdateChatData;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Response;
use Spatie\QueryBuilder\QueryBuilder;

#[Group('Чаты')]
class ChatController extends Controller
{
	public function __construct(
		private ChatService $chatService,
	) {
	}

	#[Endpoint(title: 'Список чатов пользователя')]
	#[QueryParam('filter[type]', description: 'Фильтр по типу чата (private, group)', example: 'private')]
	#[QueryParam('include', description: 'Включить связанные данные (members, lastMessage, creator)', example: 'lastMessage')]
	#[QueryParam('sort', description: 'Сортировка: id, created_at, updated_at (префикс - для убывания)', example: '-updated_at')]
	#[Response(
		status: 200,
		description: 'Список чатов с курсорной пагинацией',
		content: <<<'JSON'
    {
        "data": [
            {
                "id": 1,
                "type": "group",
                "title": "Laravel Developers",
                "created_by": 1,
                "created_at": "2026-02-28T20:21:48.000000Z",
                "updated_at": "2026-02-28T20:21:48.000000Z",
                "last_message_id": 1,
                "pivot": {
                    "user_id": 1,
                    "chat_id": 1,
                    "role": "owner",
                    "last_read_message_id": null,
                    "created_at": "2026-02-28T20:21:48.000000Z",
                    "updated_at": "2026-02-28T20:21:48.000000Z"
                }
            }
        ],
        "meta": {
            "current_page": 1,
            "per_page": 15,
            "path": "http://localhost/api/v1/chats",
            "next_cursor": "eyJpZCI6MTB9",
            "prev_cursor": null
        }
    }
    JSON
	)]

	/**
	 * Список чатов текущего пользователя
	 */
	public function index(Request $request): CursorPaginator
	{
		$user = $request->user();

		$query = QueryBuilder::for($user->chats())
			->allowedFilters(['type'])
			->allowedIncludes(['members', 'lastMessage', 'creator'])
			->allowedSorts(['id', 'created_at', 'updated_at'])
			->defaultSort('-updated_at');

		return $query->cursorPaginate(30);
	}

	#[Endpoint(title: 'Создать чат', description: 'Создаёт новый чат. Для private чата нужно передать participant_id.')]
	#[BodyParam('type', required: true, type: 'string', example: 'private', description: 'Тип чата', enum: ['private', 'group'])]
	#[BodyParam('title', required: false, type: 'string', example: 'Мой чат', description: 'Название чата (обязательно для group)')]
	#[BodyParam('participant_id', required: false, type: 'int', example: 5, description: 'ID участника для private чата')]
	#[Response(
		status: 201,
		description: 'Чат создан',
		content: [
			'id' => 1,
			'type' => 'private',
			'title' => null,
			'created_by' => 2,
			'created_at' => '2026-02-28T10:30:00.000000Z',
			'updated_at' => '2026-02-28T10:30:00.000000Z',
		]
	)]
	#[Response(status: 400, description: 'Ошибка валидации')]
	#[Response(status: 404, description: 'Пользователь не найден')]
	public function store(Request $request, CreateChatData $data): JsonResponse
	{
		$user   = $request->user();
		$result = $this->chatService->createChat($user, $data);

		return response()->json(
			$result['exists'] ?? false
			? ['message' => 'Чат с этим пользователем уже существует', 'chat' => $result['chat']]
			: ['message' => 'Чат успешно создан', 'chat' => $result['chat']],
			$result['status']
		);
	}

	#[Endpoint(title: 'Получить информацию о чате', description: 'Возвращает данные чата по ID.')]
	#[QueryParam('include', description: 'Включить связанные данные (members, lastMessage, creator)', example: 'members,lastMessage')]
	#[Response(
		status: 200,
		description: 'Данные чата',
		content: [
			'id' => 1,
			'type' => 'private',
			'title' => null,
			'created_by' => 2,
			'last_message_id' => 123,
			'created_at' => '2026-02-28T10:30:00.000000Z',
			'updated_at' => '2026-02-28T10:30:00.000000Z',
		]
	)]
	#[Response(status: 403, description: 'Нет доступа к чату')]
	#[Response(status: 404, description: 'Чат не найден')]
	public function show(Request $request, Chat $chat): JsonResponse
	{
		// Проверка: пользователь является участником чата
		if (! $chat->members()->where('user_id', $request->user()->id)->exists()) {
			abort(403, 'Not a member of this chat');
		}

		$include = $request->query('include', null);
		if ($include) {
			$response = $chat->load(explode(',', $include));
		}

		$response = $chat;

		return response()->json([
			// 'chat' => ShowChatData::collect($response),
			'chat' => $response,
		]);
	}

	#[Endpoint(title: 'Обновить чат', description: 'Обновляет название чата. Только для group чатов.')]
	#[BodyParam('title', required: false, type: 'string', example: 'Новое название', description: 'Новое название чата')]
	#[Response(
		status: 200,
		description: 'Чат обновлён',
		content: [
			'message' => 'Чат успешно обновлён',
			'chat' => ['id' => 1, 'title' => 'Новое название'],
		]
	)]
	#[Response(status: 403, description: 'Только владелец или модератор может редактировать')]
	#[Response(status: 400, description: 'Нельзя изменить title у private чата')]
	public function update(Request $request, Chat $chat, UpdateChatData $data): JsonResponse
	{
		$user   = $request->user();
		$result = $this->chatService->updateChat($chat, $user, $data);

		if (! $result['success']) {
			return response()->json(['message' => $result['error']], $result['status']);
		}

		return response()->json([
			'message' => 'Чат успешно обновлён',
			'chat' => $result['chat'],
		]);
	}

	#[Endpoint(title: 'Удалить чат', description: 'Удаляет чат. Только владелец может удалить.')]
	#[Response(
		status: 200,
		description: 'Чат удалён',
		content: [
			'status' => 'success',
			'message' => 'Чат успешно удалён',
		]
	)]
	#[Response(status: 403, description: 'Только владелец может удалить чат')]
	public function destroy(Request $request, Chat $chat): JsonResponse
	{
		$user   = $request->user();
		$result = $this->chatService->deleteChat($chat, $user);

		if (! $result['success']) {
			return response()->json(['message' => $result['error']], $result['status']);
		}

		return response()->json([
			'status' => 'success',
			'message' => $result['message'],
		]);
	}
}
