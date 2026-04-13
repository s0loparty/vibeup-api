<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\ChatFile\ChatFileResponseData;
use App\Data\ChatFile\TemporaryUrlData;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\ChatFile;
use App\Services\ChatFileService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ChatFileController extends Controller
{
    public function __construct(
        private ChatFileService $fileService,
    ) {}

    #[OA\Post(
        path: '/api/v1/chats/{chatId}/files',
        operationId: 'chat-files.store',
        summary: 'Загрузить файл в чат',
        description: 'Загружает файл или изображение в чат. Файл доступен только участникам чата через временные ссылки.',
        tags: ['Файлы чата'],
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
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'Файл для загрузки (макс. 100MB)'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Файл успешно загружен',
                content: new OA\JsonContent(ref: '#/components/schemas/ChatFileResponse')
            ),
            new OA\Response(
                response: 400,
                description: 'Ошибка: превышена квота или недопустимый файл',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Превышена квота дискового пространства (1 ГБ). Удалите старые файлы.'),
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
    public function store(Request $request, Chat $chat): JsonResponse
    {
        $user = $request->user();

        // Проверка: пользователь — участник чата
        if (! $chat->members()->where('user_id', $user->id)->exists()) {
            abort(403, 'Not a member of this chat');
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:102400'], // 100MB
        ]);

        try {
            $chatFile = $this->fileService->uploadFile($chat, $user, $validated['file']);

            return response()->json(
                ChatFileResponseData::fromModel($chatFile),
                201
            );
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    #[OA\Get(
        path: '/api/v1/chats/{chatId}/files/{fileId}/download',
        operationId: 'chat-files.download',
        summary: 'Получить временную ссылку для скачивания',
        description: 'Генерирует временную ссылку (5 минут) для скачивания файла.',
        tags: ['Файлы чата'],
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
                name: 'fileId',
                in: 'path',
                required: true,
                description: 'ID файла',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Временная ссылка сгенерирована',
                content: new OA\JsonContent(ref: '#/components/schemas/TemporaryUrlResponse')
            ),
            new OA\Response(
                response: 403,
                description: 'Нет доступа к файлу',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Not a member of this chat'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Файл не найден',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'File not found'),
                    ]
                )
            ),
        ]
    )]
    public function download(Request $request, Chat $chat, ChatFile $file): JsonResponse
    {
        $user = $request->user();

        // Проверка: пользователь — участник чата
        if (! $chat->members()->where('user_id', $user->id)->exists()) {
            abort(403, 'Not a member of this chat');
        }

        // Проверка: файл принадлежит этому чату
        if ($file->chat_id !== $chat->id) {
            abort(404, 'File not found');
        }

        $url = $this->fileService->generateTemporaryUrl($file);
        $expiresAt = Carbon::now()->addMinutes(ChatFileService::TEMPORARY_URL_LIFETIME);

        return response()->json(
            new TemporaryUrlData(
                url: $url,
                expires_at: $expiresAt->toIso8601String()
            )
        );
    }

    #[OA\Delete(
        path: '/api/v1/chats/{chatId}/files/{fileId}',
        operationId: 'chat-files.destroy',
        summary: 'Удалить файл',
        description: 'Удаляет файл из чата и S3. Только автор файла или владелец/модератор чата.',
        tags: ['Файлы чата'],
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
                name: 'fileId',
                in: 'path',
                required: true,
                description: 'ID файла',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Файл удалён',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Файл успешно удалён'),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Нет прав на удаление',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Cannot delete this file'),
                    ]
                )
            ),
        ]
    )]
    public function destroy(Request $request, Chat $chat, ChatFile $file): JsonResponse
    {
        $user = $request->user();

        // Проверка: пользователь — участник чата
        if (! $chat->members()->where('user_id', $user->id)->exists()) {
            abort(403, 'Not a member of this chat');
        }

        // Проверка: файл принадлежит этому чату
        if ($file->chat_id !== $chat->id) {
            abort(404, 'File not found');
        }

        // Проверка прав: только автор файла или владелец/модератор чата
        $member = $chat->members()->where('user_id', $user->id)->first();
        $isOwner = $file->user_id === $user->id;
        $isChatModerator = in_array($member->pivot->role, ['owner', 'moderator'], true);

        if (! $isOwner && ! $isChatModerator) {
            abort(403, 'Cannot delete this file');
        }

        $this->fileService->deleteFile($file);

        return response()->json([
            'message' => 'Файл успешно удалён',
        ]);
    }
}
