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
use OpenApi\Attributes as OA;
use Spatie\QueryBuilder\QueryBuilder;

class MessageController extends Controller
{
    #[OA\Get(
        path: '/api/v1/chats/{chatId}/messages',
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
                name: 'filter[before]',
                in: 'query',
                description: 'Сообщения до указанного ID (для пагинации)',
                required: false,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'filter[after]',
                in: 'query',
                description: 'Сообщения после указанного ID (для пагинации)',
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
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Список сообщений с курсорной пагинацией',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Message')),
                        new OA\Property(property: 'meta', ref: '#/components/schemas/CursorPaginationMeta'),
                    ]
                )
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
    public function index(Request $request, Chat $chat): CursorPaginator
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

    #[OA\Post(
        path: '/api/v1/chats/{chatId}/messages',
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
                content: new OA\JsonContent(ref: '#/components/schemas/Message')
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
    public function store(Request $request, Chat $chat, CreateMessageData $data): Message
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

        return $message->load('sender');
    }

    #[OA\Get(
        path: '/api/v1/chats/{chatId}/messages/{message}',
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
                content: new OA\JsonContent(ref: '#/components/schemas/Message')
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
    public function show(Request $request, Message $message): Message
    {
        $chat = $message->chat;
        if (! $chat->members()->where('user_id', $request->user()->id)->exists()) {
            abort(403, 'Not a member of this chat');
        }

        return $message->load('sender', 'replyTo');
    }

    #[OA\Put(
        path: '/api/v1/chats/{chatId}/messages/{message}',
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
                content: new OA\JsonContent(ref: '#/components/schemas/Message')
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
    public function update(Request $request, Message $message, UpdateMessageData $data): Message
    {
        if ($message->sender_id !== $request->user()->id) {
            abort(403, 'Cannot edit this message');
        }

        $message->update([
            'body' => $data->body ?? $message->body,
            'edited_at' => now(),
        ]);

        return $message->fresh('sender', 'replyTo');
    }

    #[OA\Delete(
        path: '/api/v1/chats/{chatId}/messages/{message}',
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
}
