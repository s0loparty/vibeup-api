<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\LoginUserAction;
use App\Actions\Auth\RegisterUserAction;
use App\Data\Auth\AuthTokenData;
use App\Data\Auth\LoginUserData;
use App\Data\Auth\RegisterUserData;
use App\Data\Auth\UserResponseData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/v1/auth/register',
        operationId: 'auth.register',
        summary: 'Регистрация нового пользователя',
        description: 'Создаёт нового пользователя и возвращает токен доступа',
        tags: ['Авторизация'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', minLength: 8, example: 'secret123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Успешная регистрация',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                        new OA\Property(property: 'token', ref: '#/components/schemas/AuthToken'),
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
    public function register(RegisterUserData $data, RegisterUserAction $action): JsonResponse
    {
        $user = $action->execute($data);
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => UserResponseData::fromModel($user),
            'token' => new AuthTokenData($token),
        ], 201);
    }

    #[OA\Post(
        path: '/api/v1/auth/login',
        operationId: 'auth.login',
        summary: 'Вход',
        description: 'Аутентификация пользователя и получение токена доступа',
        tags: ['Авторизация'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный вход',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                        new OA\Property(property: 'token', ref: '#/components/schemas/AuthToken'),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Неверные учётные данные',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Неверный email или пароль'),
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
    public function login(LoginUserData $data, LoginUserAction $action): JsonResponse
    {
        $user = $action->execute(
            $data->email,
            $data->password
        );

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => UserResponseData::fromModel($user),
            'token' => new AuthTokenData($token),
        ]);
    }

    #[OA\Post(
        path: '/api/v1/auth/logout',
        operationId: 'auth.logout',
        summary: 'Выход',
        description: 'Уничтожает текущий токен доступа (выход из системы)',
        tags: ['Авторизация'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный выход',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Успешный выход'),
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
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Успешный выход']);
    }
}
