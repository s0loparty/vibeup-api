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
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;

#[Group('Авторизация')]
class AuthController extends Controller
{
	#[Endpoint(title: 'Регистрация', description: 'Регистрирует нового пользователя и возвращает токен доступа.')]
	#[BodyParam('email', required: true, type: 'string', example: 'user@example.com', description: 'Email адрес пользователя')]
	#[BodyParam('password', required: true, type: 'string', example: 'secret123', description: 'Пароль (минимум 8 символов)')]
	#[Response(
		status: 201,
		description: 'Пользователь зарегистрирован',
		content: [
			'user' => [
				'id' => 1,
				'name' => 'John Doe',
				'email' => 'user@example.com',
				'email_verified_at' => null,
				'created_at' => '2026-03-11T11:36:31.000000Z',
				'updated_at' => '2026-03-11T11:36:31.000000Z',
			],
			'token' => ['token' => '1|abc123xyz456', 'type' => 'Bearer'],
		]
	)]
	#[Response(
		status: 422,
		description: 'Ошибка валидации',
		content: [
			'message' => 'The email field is required. (and 1 more error)',
			'errors' => [
				'email' => ['The email field is required.'],
				'password' => ['The password field is required.'],
			],
		]
	)]
	#[Response(
		status: 409,
		description: 'Пользователь с таким email уже существует',
		content: ['message' => 'Пользователь с таким email уже зарегистрирован']
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

	#[Endpoint(title: 'Вход', description: 'Аутентификация пользователя и получение токена доступа.')]
	#[BodyParam('email', required: true, type: 'string', example: 'user@example.com', description: 'Email адрес пользователя')]
	#[BodyParam('password', required: true, type: 'string', example: 'password', description: 'Пароль пользователя')]
	#[Response(
		status: 200,
		description: 'Успешный вход',
		content: [
			'user' => [
				'id' => 1,
				'name' => 'John Doe',
				'email' => 'user@example.com',
				'email_verified_at' => '2026-03-11T11:36:31.000000Z',
				'created_at' => '2026-03-11T11:36:31.000000Z',
				'updated_at' => '2026-03-11T11:36:31.000000Z',
			],
			'token' => ['token' => '1|abc123xyz456', 'type' => 'Bearer'],
		]
	)]
	#[Response(
		status: 401,
		description: 'Неверные учётные данные',
		content: ['message' => 'Неверный email или пароль']
	)]
	#[Response(
		status: 422,
		description: 'Ошибка валидации',
		content: [
			'message' => 'The email field is required. (and 1 more error)',
			'errors' => [
				'email' => ['The email field is required.'],
				'password' => ['The password field is required.'],
			],
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

	#[Endpoint(title: 'Выход', description: 'Уничтожает текущий токен доступа (выход из системы).')]
	#[Response(
		status: 200,
		content: ['message' => 'Успешный выход']
	)]
	#[Response(
		status: 401,
		description: 'Пользователь не аутентифицирован',
		content: ['message' => 'Unauthenticated.']
	)]
	public function logout(Request $request): JsonResponse
	{
		$request->user()->currentAccessToken()->delete();

		return response()->json(['message' => 'Успешный выход']);
	}
}
