<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Chat\CreateChatData;
use App\Data\Chat\UpdateChatData;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use OpenApi\Attributes as OA;
use Spatie\QueryBuilder\QueryBuilder;

class ChatController extends Controller
{
    public function __construct(
        private ChatService $chatService,
    ) {}

    #[OA\Get(
        path: '/api/v1/chats',
        summary: 'Список чатов пользователя',
        description: 'Возвращает список чатов пользователя с курсорной пагинацией',
        tags: ['Чаты'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'filter[type]',
                in: 'query',
                description: 'Фильтр по типу чата',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['private', 'group'])
            ),
            new OA\Parameter(
                name: 'include',
                in: 'query',
                description: 'Включить связанные данные (members, lastMessage, creator)',
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
                description: 'Список чатов с курсорной пагинацией',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Chat')),
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
        ]
    )]
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

    #[OA\Post(
        path: '/api/v1/chats',
        summary: 'Создать чат',
        description: 'Создаёт новый чат. Для private чата нужно передать participant_id',
        tags: ['Чаты'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['private', 'group'], example: 'private', description: 'Тип чата'),
                    new OA\Property(property: 'title', type: 'string', nullable: true, example: 'Мой чат', description: 'Название чата (обязательно для group)'),
                    new OA\Property(property: 'participant_id', type: 'integer', nullable: true, example: 5, description: 'ID участника для private чата'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Чат создан',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Чат успешно создан'),
                        new OA\Property(property: 'chat', ref: '#/components/schemas/Chat'),
                    ]
                )
            ),
            new OA\Response(
                response: 200,
                description: 'Чат уже существует (для private чатов)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Чат с этим пользователем уже существует'),
                        new OA\Property(property: 'chat', ref: '#/components/schemas/Chat'),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Ошибка валидации',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
            ),
            new OA\Response(
                response: 404,
                description: 'Пользователь не найден',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Пользователь не найден'),
                    ]
                )
            ),
        ]
    )]
    public function store(Request $request, CreateChatData $data): JsonResponse
    {
        $user = $request->user();
        $result = $this->chatService->createChat($user, $data);

        return response()->json(
            $result['exists'] ?? false
                ? ['message' => 'Чат с этим пользователем уже существует', 'chat' => $result['chat']]
                : ['message' => 'Чат успешно создан', 'chat' => $result['chat']],
            $result['status']
        );
    }

    #[OA\Get(
        path: '/api/v1/chats/{chat}',
        summary: 'Получить информацию о чате',
        description: 'Возвращает данные чата по ID',
        tags: ['Чаты'],
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
                name: 'include',
                in: 'query',
                description: 'Включить связанные данные (members, lastMessage, creator)',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Данные чата',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'chat', ref: '#/components/schemas/Chat'),
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
            new OA\Response(
                response: 404,
                description: 'Чат не найден',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Чат не найден'),
                    ]
                )
            ),
        ]
    )]
    public function show(Request $request, Chat $chat): JsonResponse
    {
        if (! $chat->members()->where('user_id', $request->user()->id)->exists()) {
            abort(403, 'Not a member of this chat');
        }

        $include = $request->query('include', null);
        if ($include) {
            $chat->load(explode(',', $include));
        }

        return response()->json(['chat' => $chat]);
    }

    #[OA\Put(
        path: '/api/v1/chats/{chat}',
        summary: 'Обновить чат',
        description: 'Обновляет название чата. Только для group чатов',
        tags: ['Чаты'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'chat',
                in: 'path',
                required: true,
                description: 'ID чата',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Новое название', description: 'Новое название чата'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Чат обновлён',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Чат успешно обновлён'),
                        new OA\Property(property: 'chat', ref: '#/components/schemas/Chat'),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Нельзя изменить title у private чата',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Нельзя изменить название для private чата'),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Только владелец или модератор может редактировать',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Only owner or moderator can edit this chat'),
                    ]
                )
            ),
        ]
    )]
    public function update(Request $request, Chat $chat, UpdateChatData $data): JsonResponse
    {
        $user = $request->user();
        $result = $this->chatService->updateChat($chat, $user, $data);

        if (! $result['success']) {
            return response()->json(['message' => $result['error']], $result['status']);
        }

        return response()->json([
            'message' => 'Чат успешно обновлён',
            'chat' => $result['chat'],
        ]);
    }

    #[OA\Delete(
        path: '/api/v1/chats/{chat}',
        summary: 'Удалить чат',
        description: 'Удаляет чат. Только владелец может удалить',
        tags: ['Чаты'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'chat',
                in: 'path',
                required: true,
                description: 'ID чата',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Чат удалён',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Чат успешно удалён'),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Только владелец может удалить чат',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Only owner can delete this chat'),
                    ]
                )
            ),
        ]
    )]
    public function destroy(Request $request, Chat $chat): JsonResponse
    {
        $user = $request->user();
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
