<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Message\CreateMessageData;
use App\Data\Message\UpdateMessageData;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Spatie\QueryBuilder\QueryBuilder;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Response;

#[Group('Сообщения')]
class MessageController extends Controller
{
	#[Endpoint(title: 'Список сообщений чата')]
	#[QueryParam('filter[type]', description: 'Фильтр по типу сообщения (text, image, video, etc.)')]
	#[QueryParam('filter[sender_id]', description: 'Фильтр по ID отправителя', example: 1)]
	#[QueryParam('filter[before]', description: 'Сообщения до указанного ID (для пагинации)', example: 123)]
	#[QueryParam('filter[after]', description: 'Сообщения после указанного ID (для пагинации)', example: 100)]
	#[QueryParam('include', description: 'Включить связанные данные (sender, replyTo, chat)', example: 'sender,replyTo')]
	#[QueryParam('sort', description: 'Сортировка: id, created_at, updated_at (префикс - для убывания)', example: '-id')]
	#[QueryParam('fields[messages]', description: 'Выбрать конкретные поля', example: 'id,body,type,created_at')]
	#[Response(
		status: 200,
		description: 'Список сообщений с курсорной пагинацией',
		content: <<<'JSON'
		{
			"data": [
				{
					"id": 53,
					"chat_id": 2,
					"sender_id": 1,
					"type": "text",
					"body": "Привет, как дела?",
					"reply_to_id": null,
					"edited_at": null,
					"created_at": "2026-03-11T11:36:31.000000Z",
					"updated_at": "2026-03-11T11:36:31.000000Z",
					"sender": {
						"id": 1,
						"name": "John",
						"email": "john@example.com"
					}
				}
			],
			"path": "http://localhost/api/v1/chats/2/messages",
			"per_page": 30,
			"next_cursor": "eyJpZCI6NTN9",
			"next_page_url": "http://localhost/api/v1/chats/2/messages?cursor=eyJpZCI6NTN9",
			"prev_cursor": null,
			"prev_page_url": null
		}
		JSON
	)]
	/**
	 * Список сообщений чата (от старых к новым для пагинации)
	 */
	public function index(Request $request, Chat $chat): CursorPaginator
	{
		// Проверка: пользователь является участником чата
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

		$items = collect($paginator->items())
			->reverse()
			->values()
			->all();

		return new CursorPaginator(
			$items,
			$paginator->perPage(),
			$paginator->cursor(),
			[
				'path' => $request->url(),
				'parameters' => $request->query(),
			]
		);
	}

	/**
	 * Создать сообщение
	 */
	#[Endpoint(title: 'Создать сообщение', description: 'Отправляет новое сообщение в чат.')]
	#[BodyParam('type', required: true, type: 'string', example: 'text', description: 'Тип сообщения: text, image, video, audio, file', enum: ['text', 'image', 'video', 'audio', 'file'])]
	#[BodyParam('body', required: true, type: 'string', example: 'Привет, как дела?', description: 'Текст сообщения или описание медиа')]
	#[BodyParam('reply_to_id', required: false, type: 'int', example: 123, description: 'ID сообщения, на которое отвечаем')]
	#[Response(
		status: 201,
		description: 'Сообщение создано',
		content: <<<'JSON'
		{
			"id": 456,
			"chat_id": 1,
			"sender_id": 2,
			"type": "text",
			"body": "Привет, как дела?",
			"reply_to_id": null,
			"edited_at": null,
			"created_at": "2026-03-11T11:36:31.000000Z",
			"updated_at": "2026-03-11T11:36:31.000000Z",
			"sender": {
				"id": 2,
				"name": "John",
				"email": "john@example.com",
				"created_at": "2026-02-28T10:30:00.000000Z",
				"updated_at": "2026-02-28T10:30:00.000000Z"
			}
		}
		JSON
	)]
	#[Response(status: 403, description: 'Пользователь не является участником чата', content: ['message' => 'Not a member of this chat'])]
	#[Response(status: 422, description: 'Ошибка валидации', content: [
		'message' => 'The body field is required.',
		'errors' => ['body' => ['The body field is required.']],
	])]
	public function store(Request $request, Chat $chat, CreateMessageData $data): Message
	{
		// Проверка: пользователь является участником чата
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

		// Обновляем last_message_id
		$chat->update(['last_message_id' => $message->id]);

		// TODO: send broadcast event

		return $message->load('sender');
	}

	/**
	 * Показать одно сообщение
	 */
	#[Endpoint(title: 'Показать сообщение', description: 'Возвращает одно сообщение по ID.')]
	#[Response(
		status: 200,
		description: 'Сообщение найдено',
		content: <<<'JSON'
		{
			"id": 456,
			"chat_id": 1,
			"sender_id": 2,
			"type": "text",
			"body": "Привет, как дела?",
			"reply_to_id": null,
			"edited_at": null,
			"created_at": "2026-03-11T11:36:31.000000Z",
			"updated_at": "2026-03-11T11:36:31.000000Z",
			"sender": {
				"id": 2,
				"name": "John",
				"email": "john@example.com"
			},
			"reply_to": null
		}
		JSON
	)]
	#[Response(status: 403, description: 'Пользователь не является участником чата')]
	#[Response(status: 404, description: 'Сообщение не найдено')]
	public function show(Request $request, Message $message): Message
	{
		// Проверка: пользователь является участником чата
		$chat = $message->chat;
		if (! $chat->members()->where('user_id', $request->user()->id)->exists()) {
			abort(403, 'Not a member of this chat');
		}

		return $message->load('sender', 'replyTo');
	}

	/**
	 * Редактировать сообщение
	 */
	#[Endpoint(title: 'Редактировать сообщение', description: 'Обновляет текст сообщения. Только автор может редактировать.')]
	#[BodyParam('body', required: false, type: 'string', example: 'Обновлённый текст', description: 'Новый текст сообщения')]
	#[Response(
		status: 200,
		description: 'Сообщение обновлено',
		content: <<<'JSON'
		{
			"id": 456,
			"chat_id": 1,
			"sender_id": 2,
			"type": "text",
			"body": "Обновлённый текст",
			"reply_to_id": null,
			"edited_at": "2026-03-11T12:00:00.000000Z",
			"created_at": "2026-03-11T11:36:31.000000Z",
			"updated_at": "2026-03-11T12:00:00.000000Z",
			"sender": {
				"id": 2,
				"name": "John",
				"email": "john@example.com"
			}
		}
		JSON
	)]
	#[Response(status: 403, description: 'Только автор может редактировать')]
	public function update(Request $request, Message $message, UpdateMessageData $data): Message
	{
		// Проверка: только автор может редактировать
		if ($message->sender_id !== $request->user()->id) {
			abort(403, 'Cannot edit this message');
		}

		$message->update([
			'body' => $data->body ?? $message->body,
			'edited_at' => now(),
		]);

		return $message->fresh('sender', 'replyTo');
	}

	/**
	 * Удалить сообщение
	 */
	#[Endpoint(title: 'Удалить сообщение', description: 'Удаляет сообщение. Только автор или модератор/владелец.')]
	#[Response(status: 200, description: 'Сообщение удалено', content: [
		'status' => 'success',
		'message' => 'The message was successfully deleted.',
	])]
	#[Response(status: 403, description: 'Нет прав на удаление')]
	public function destroy(Request $request, Message $message): JsonResponse
	{
		// Проверка: только автор или модератор/владелец чата
		$chat   = $message->chat;
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
			'message' => 'The message was successfully deleted.'
		]);
	}
}
