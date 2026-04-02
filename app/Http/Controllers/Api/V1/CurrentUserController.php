<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

class CurrentUserController extends Controller
{
    #[OA\Get(
        path: '/api/v1/user',
        operationId: 'user.show',
        summary: 'Получить текущего пользователя',
        description: 'Возвращает данные текущего аутентифицированного пользователя',
        tags: ['Текущий пользователь'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Данные получены',
                content: new OA\JsonContent(ref: '#/components/schemas/User')
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
    public function show(): User
    {
        return Auth::user();
    }

    #[OA\Put(
        path: '/api/v1/user',
        operationId: 'user.update',
        summary: 'Обновить текущего пользователя',
        description: 'Обновляет данные текущего аутентифицированного пользователя',
        tags: ['Текущий пользователь'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe', description: 'Имя пользователя'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com', description: 'Email адрес'),
                    new OA\Property(property: 'password', type: 'string', minLength: 8, example: 'password', description: 'Новый пароль (мин. 8 символов)'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Данные обновлены',
                content: new OA\JsonContent(ref: '#/components/schemas/User')
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
                response: 422,
                description: 'Ошибка валидации',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
            ),
        ]
    )]
    public function update(Request $request): User
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $user->update(array_filter($validated));

        return $user->fresh();
    }

    #[OA\Delete(
        path: '/api/v1/user',
        operationId: 'user.destroy',
        summary: 'Удалить текущего пользователя',
        description: 'Безвозвратно удаляет аккаунт текущего пользователя',
        tags: ['Текущий пользователь'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Аккаунт удалён',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Аккаунт успешно удалён'),
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

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'Аккаунт успешно удалён',
        ]);
    }
}
