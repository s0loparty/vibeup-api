<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Message\CreateMessageData;
use App\Data\Message\UpdateMessageData;
use App\Http\Controllers\Controller;
use App\Http\Resources\CursorPaginatedResource;
use App\Http\Resources\Message\PublicMessageResource;
use App\Http\Resources\MessageSearchResource;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use OpenApi\Attributes as OA;
use Spatie\QueryBuilder\QueryBuilder;

class MessageController extends Controller
{
	#[OA\Get(
		path: '/api/v1/chats/{chatId}/messages',
		operationId: 'messages.index',
		summary: 'Список сообщений чата',
		description: 'Возвращает список сообщений чата с курсорной пагинацией',
		tags: ['Сообщения'],
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\Parameter(
				name: 'chatId',
				in: 'path',
				required: true,
				description: 'ID чата',
				schema: new OA\Schema(type: 'integer')
			),
			new OA\Parameter(
				name: 'cursor',
				in: 'query',
				description: 'Курсор для пагинации (значение из meta.next_cursor или meta.prev_cursor)',
				required: false,
				schema: new OA\Schema(type: 'string', example: 'eyJpZCI6NTN9')
			),
			new OA\Parameter(
				name: 'filter[type]',
				in: 'query',
				description: 'Фильтр по типу сообщения',
				required: false,
				schema: new OA\Schema(type: 'string', enum: ['text', 'image', 'video', 'audio', 'file'])
			),
			new OA\Parameter(
				name: 'filter[sender_id]',
				in: 'query',
				description: 'Фильтр по ID отправителя',
				required: false,
				schema: new OA\Schema(type: 'integer')
			),
			new OA\Parameter(
				name: 'include',
				in: 'query',
				description: 'Включить связанные данные (sender, replyTo, chat)',
				required: false,
				schema: new OA\Schema(type: 'string')
			),
			new OA\Parameter(
				name: 'sort',
				in: 'query',
				description: 'Сортировка: id, created_at, updated_at (префикс - для убывания)',
				required: false,
				schema: new OA\Schema(type: 'string', example: '-created_at')
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: 'Список сообщений с курсорной пагинацией',
				content: new OA\JsonContent(ref: '#/components/schemas/PaginatedMessagesResponse')
			),
			new OA\Response(
				response: 401,
				description: 'Пользователь не аутентифицирован',
				content: new OA\JsonContent(
					properties: [
						new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
					]
				)
			),
			new OA\Response(
				response: 403,
				description: 'Нет доступа к чату',
				content: new OA\JsonContent(
					properties: [
						new OA\Property(property: 'message', type: 'string', example: 'Not a member of this chat'),
					]
				)
			),
		]
	)]
	public function index(Request $request, Chat $chat): CursorPaginatedResource
	{
		if (! $chat->members()->where('user_id', $request->user()->id)->exists()) {
			abort(403, 'Not a member of this chat');
		}

		$query = QueryBuilder::for(Message::class)
			->allowedFilters($chat->messages()->getModel()->allowedFilters())
			->allowedIncludes($chat->messages()->getModel()->allowedIncludes())
			->allowedSorts($chat->messages()->getModel()->allowedSorts())
			->defaultSort('-created_at')
			->where('chat_id', $chat->id);

		$paginator = $query->cursorPaginate(30);

		// убрал reverse
		$items = PublicMessageResource::collection(
			collect($paginator->items())
		);

		return new CursorPaginatedResource($paginator, ['data' => $items]);
	}

	#[OA\Post(
		path: '/api/v1/chats/{chatId}/messages',
		operationId: 'messages.store',
		summary: 'Создать сообщение',
		description: 'Отправляет новое сообщение в чат',
		tags: ['Сообщения'],
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\Parameter(
				name: 'chatId',
				in: 'path',
				required: true,
				description: 'ID чата',
				schema: new OA\Schema(type: 'integer')
			),
		],
		requestBody: new OA\RequestBody(
			required: true,
			content: new OA\JsonContent(
				required: ['type', 'body'],
				properties: [
					new OA\Property(property: 'type', type: 'string', enum: ['text', 'image', 'video', 'audio', 'file'], example: 'text', description: 'Тип сообщения'),
					new OA\Property(property: 'body', type: 'string', example: 'Привет, как дела?', description: 'Текст сообщения или описание медиа'),
					new OA\Property(property: 'reply_to_id', type: 'integer', nullable: true, example: 123, description: 'ID сообщения, на которое отвечаем'),
				]
			)
		),
		responses: [
			new OA\Response(
				response: 201,
				description: 'Сообщение создано',
				content: new OA\JsonContent(ref: '#/components/schemas/PublicMessage')
			),
			new OA\Response(
				response: 403,
				description: 'Пользователь не является участником чата',
				content: new OA\JsonContent(
					properties: [
						new OA\Property(property: 'message', type: 'string', example: 'Not a member of this chat'),
					]
				)
			),
			new OA\Response(
				response: 422,
				description: 'Ошибка валидации',
				content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
			),
		]
	)]
	public function store(Request $request, Chat $chat, CreateMessageData $data): PublicMessageResource
	{
		if (! $chat->members()->where('user_id', $request->user()->id)->exists()) {
			abort(403, 'Not a member of this chat');
		}

		$message = Message::create([
			'chat_id' => $chat->id,
			'sender_id' => $request->user()->id,
			'type' => $data->type,
			'body' => $data->body,
			'reply_to_id' => $data->reply_to_id,
		]);

		$chat->update(['last_message_id' => $message->id]);

		return new PublicMessageResource($message->load('sender', 'replyTo'));
	}

	#[OA\Get(
		path: '/api/v1/chats/{chatId}/messages/{message}',
		operationId: 'messages.show',
		summary: 'Показать сообщение',
		description: 'Возвращает одно сообщение по ID',
		tags: ['Сообщения'],
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\Parameter(
				name: 'chatId',
				in: 'path',
				required: true,
				description: 'ID чата',
				schema: new OA\Schema(type: 'integer')
			),
			new OA\Parameter(
				name: 'message',
				in: 'path',
				required: true,
				description: 'ID сообщения',
				schema: new OA\Schema(type: 'integer')
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: 'Сообщение найдено',
				content: new OA\JsonContent(ref: '#/components/schemas/PublicMessage')
			),
			new OA\Response(
				response: 403,
				description: 'Пользователь не является участником чата',
				content: new OA\JsonContent(
					properties: [
						new OA\Property(property: 'message', type: 'string', example: 'Not a member of this chat'),
					]
				)
			),
			new OA\Response(
				response: 404,
				description: 'Сообщение не найдено',
				content: new OA\JsonContent(
					properties: [
						new OA\Property(property: 'message', type: 'string', example: 'Сообщение не найдено'),
					]
				)
			),
		]
	)]
	public function show(Request $request, Message $message): PublicMessageResource
	{
		$chat = $message->chat;
		if (! $chat->members()->where('user_id', $request->user()->id)->exists()) {
			abort(403, 'Not a member of this chat');
		}

		return new PublicMessageResource($message->load('sender', 'replyTo'));
	}

	#[OA\Put(
		path: '/api/v1/chats/{chatId}/messages/{message}',
		operationId: 'messages.update',
		summary: 'Редактировать сообщение',
		description: 'Обновляет текст сообщения. Только автор может редактировать',
		tags: ['Сообщения'],
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\Parameter(
				name: 'chatId',
				in: 'path',
				required: true,
				description: 'ID чата',
				schema: new OA\Schema(type: 'integer')
			),
			new OA\Parameter(
				name: 'message',
				in: 'path',
				required: true,
				description: 'ID сообщения',
				schema: new OA\Schema(type: 'integer')
			),
		],
		requestBody: new OA\RequestBody(
			content: new OA\JsonContent(
				properties: [
					new OA\Property(property: 'body', type: 'string', example: 'Обновлённый текст', description: 'Новый текст сообщения'),
				]
			)
		),
		responses: [
			new OA\Response(
				response: 200,
				description: 'Сообщение обновлено',
				content: new OA\JsonContent(ref: '#/components/schemas/PublicMessage')
			),
			new OA\Response(
				response: 403,
				description: 'Только автор может редактировать',
				content: new OA\JsonContent(
					properties: [
						new OA\Property(property: 'message', type: 'string', example: 'Cannot edit this message'),
					]
				)
			),
		]
	)]
	public function update(Request $request, Message $message, UpdateMessageData $data): PublicMessageResource
	{
		if ($message->sender_id !== $request->user()->id) {
			abort(403, 'Cannot edit this message');
		}

		$message->update([
			'body' => $data->body ?? $message->body,
			'edited_at' => now(),
		]);

		return new PublicMessageResource($message->fresh(['sender', 'replyTo']));
	}

	#[OA\Delete(
		path: '/api/v1/chats/{chatId}/messages/{message}',
		operationId: 'messages.destroy',
		summary: 'Удалить сообщение',
		description: 'Удаляет сообщение. Только автор или модератор/владелец',
		tags: ['Сообщения'],
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\Parameter(
				name: 'chatId',
				in: 'path',
				required: true,
				description: 'ID чата',
				schema: new OA\Schema(type: 'integer')
			),
			new OA\Parameter(
				name: 'message',
				in: 'path',
				required: true,
				description: 'ID сообщения',
				schema: new OA\Schema(type: 'integer')
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: 'Сообщение удалено',
				content: new OA\JsonContent(
					properties: [
						new OA\Property(property: 'status', type: 'string', example: 'success'),
						new OA\Property(property: 'message', type: 'string', example: 'The message was successfully deleted.'),
					]
				)
			),
			new OA\Response(
				response: 403,
				description: 'Нет прав на удаление',
				content: new OA\JsonContent(
					properties: [
						new OA\Property(property: 'message', type: 'string', example: 'Cannot delete this message'),
					]
				)
			),
		]
	)]
	public function destroy(Request $request, Message $message): JsonResponse
	{
		$chat = $message->chat;
		$member = $chat->members()->where('user_id', $request->user()->id)->first();

		if (! $member || (
			$message->sender_id !== $request->user()->id &&
			! in_array($member->pivot->role, ['owner', 'moderator'])
		)) {
			abort(403, 'Cannot delete this message');
		}

		$message->delete();

		return response()->json([
			'status' => 'success',
			'message' => 'The message was successfully deleted.',
		]);
	}

	#[OA\Get(
		path: '/api/v1/chats/{chat}/messages/context/{message}',
		operationId: 'messages.context',
		summary: 'Получить сообщение с контекстом',
		description: 'Возвращает все сообщения после целевого, само целевое сообщение и N предыдущих (до целевого), а также курсоры для пагинации',
		tags: ['Сообщения'],
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\Parameter(
				name: 'chat',
				in: 'path',
				required: true,
				description: 'ID чата',
				schema: new OA\Schema(type: 'integer')
			),
			new OA\Parameter(
				name: 'message',
				in: 'path',
				required: true,
				description: 'ID сообщения',
				schema: new OA\Schema(type: 'integer')
			),
			new OA\Parameter(
				name: 'limit',
				in: 'query',
				required: false,
				description: 'Количество сообщений перед целевым (по умолчанию 30)',
				schema: new OA\Schema(type: 'integer', default: 30)
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: 'Успешный ответ',
				content: new OA\JsonContent(
					properties: [
						new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PublicMessage')),
						new OA\Property(property: 'prev_cursor', type: 'string', nullable: true),
						new OA\Property(property: 'next_cursor', type: 'string', nullable: true),
						new OA\Property(property: 'target_message_id', type: 'integer'),
					]
				)
			),
			new OA\Response(response: 403, description: 'Нет доступа'),
			new OA\Response(response: 404, description: 'Сообщение не найдено'),
		]
	)]
	public function context(Request $request, Chat $chat, int $message)
	{
		return app(\App\Services\MessageService::class)->context($request, $chat, $message);
	}

	#[OA\Get(
		path: '/api/v1/chats/{chat}/messages/search',
		operationId: 'messages.search',
		summary: 'Поиск сообщений в чате',
		description: 'Выполняет нечеткий поиск по тексту сообщений в указанном чате с использованием алгоритма Левенштейна. Поддерживает толерантность к опечаткам. Результаты фильтруются по порогу релевантности (_score >= 0.3)',
		tags: ['Сообщения'],
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\Parameter(
				name: 'chat',
				in: 'path',
				required: true,
				description: 'ID чата',
				schema: new OA\Schema(type: 'integer')
			),
			new OA\Parameter(
				name: 'q',
				in: 'query',
				required: true,
				description: 'Поисковый запрос',
				schema: new OA\Schema(type: 'string', minLength: 1)
			),
			new OA\Parameter(
				name: 'page',
				in: 'query',
				required: false,
				description: 'Номер страницы',
				schema: new OA\Schema(type: 'integer', default: 1)
			),
			new OA\Parameter(
				name: 'per_page',
				in: 'query',
				required: false,
				description: 'Количество результатов на странице (по умолчанию 15)',
				schema: new OA\Schema(type: 'integer', default: 15)
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: 'Успешный ответ',
				content: new OA\JsonContent(
					properties: [
						new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/MessageSearch')),
						new OA\Property(property: 'current_page', type: 'integer'),
						new OA\Property(property: 'per_page', type: 'integer'),
						new OA\Property(property: 'total', type: 'integer'),
						new OA\Property(property: 'last_page', type: 'integer'),
						new OA\Property(
							property: 'links',
							type: 'object',
							properties: [
								new OA\Property(property: 'first', type: 'string', nullable: true),
								new OA\Property(property: 'last', type: 'string', nullable: true),
								new OA\Property(property: 'prev', type: 'string', nullable: true),
								new OA\Property(property: 'next', type: 'string', nullable: true),
							]
						),
						new OA\Property(
							property: 'meta',
							type: 'object',
							properties: [
								new OA\Property(property: 'current_page', type: 'integer'),
								new OA\Property(property: 'from', type: 'integer', nullable: true),
								new OA\Property(property: 'last_page', type: 'integer'),
								new OA\Property(property: 'path', type: 'string'),
								new OA\Property(property: 'per_page', type: 'integer'),
								new OA\Property(property: 'to', type: 'integer', nullable: true),
								new OA\Property(property: 'total', type: 'integer'),
							]
						),
					]
				)
			),
			new OA\Response(
				response: 400,
				description: 'Не указан поисковый запрос',
				content: new OA\JsonContent(
					properties: [
						new OA\Property(property: 'message', type: 'string', example: 'Search query parameter "q" is required'),
					]
				)
			),
			new OA\Response(response: 403, description: 'Нет доступа к чату'),
			new OA\Response(response: 404, description: 'Чат не найден'),
		]
	)]
	public function search(Request $request, Chat $chat): JsonResponse
	{
		$searchPattern = $request->input("q");

		$messages = Message::search($searchPattern)
			->highlight('<mark>', '</mark>')
			->searchIn(['body' => 10])
			->filter('chat_id', $chat->id)
			->using('levenshtein')
			->typoTolerance(1)
			->simplePaginate();

		$messagesResource = MessageSearchResource::collection($messages->items())->toArray($request);
		$filteredItems = collect($messagesResource)
			->filter(fn($item) => ($item['_score'] ?? 0) >= 0.3)
			->values();

		$filteredPaginator = new LengthAwarePaginator(
			items: $filteredItems,
			total: $filteredItems->count(),
			perPage: $messages->perPage(),
			currentPage: $messages->currentPage(),
		);

		return response()->json($filteredPaginator, 200);
	}
}
