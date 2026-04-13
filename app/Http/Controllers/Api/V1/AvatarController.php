<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AvatarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AvatarController extends Controller
{
    public function __construct(
        private AvatarService $avatarService,
    ) {}

    #[OA\Post(
        path: '/api/v1/user/avatar',
        operationId: 'avatar.upload',
        summary: 'Загрузить или заменить аватарку',
        description: 'Загружает новую аватарку. Старая аватарка автоматически удаляется.',
        tags: ['Аватарки'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['avatar'],
                    properties: [
                        new OA\Property(property: 'avatar', type: 'string', format: 'binary', description: 'Файл аватарки (jpg, png, webp, макс. 2MB)'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Аватарка загружена',
                content: new OA\JsonContent(ref: '#/components/schemas/AvatarResponse')
            ),
            new OA\Response(
                response: 400,
                description: 'Ошибка валидации (неподдерживаемый формат или размер > 2MB)',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
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
    public function upload(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'avatar' => ['required', 'file', 'mimes:jpeg,png,webp', 'max:2048'],
        ]);

        $media = $this->avatarService->uploadAvatar($user, $request->file('avatar'));

        return response()->json([
            'original' => $media->getFullUrl(),
            'thumbnail' => $media->getFullUrl('thumbnail'),
        ]);
    }

    #[OA\Delete(
        path: '/api/v1/user/avatar',
        operationId: 'avatar.destroy',
        summary: 'Удалить аватарку',
        description: 'Удаляет текущую аватарку пользователя',
        tags: ['Аватарки'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Аватарка удалена',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Аватарка успешно удалена'),
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
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->avatarService->deleteAvatar($user);

        return response()->json([
            'message' => 'Аватарка успешно удалена',
        ]);
    }

    #[OA\Get(
        path: '/api/v1/users/{userId}/avatar',
        operationId: 'avatar.show',
        summary: 'Получить аватарку пользователя',
        description: 'Возвращает URL оригинальной аватарки и thumbnail',
        tags: ['Аватарки'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'userId',
                in: 'path',
                required: true,
                description: 'ID пользователя',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Данные аватарки',
                content: new OA\JsonContent(ref: '#/components/schemas/AvatarResponse')
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
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'original' => $this->avatarService->getAvatarUrl($user),
            'thumbnail' => $this->avatarService->getThumbnailUrl($user),
        ]);
    }
}
