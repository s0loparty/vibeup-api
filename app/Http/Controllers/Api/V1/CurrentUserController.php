<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;

#[Group('Текущий пользователь')]
class CurrentUserController extends Controller
{
	#[Endpoint(
		title: 'Получить текущего пользователя',
		description: 'Возвращает данные текущего аутентифицированного пользователя.'
	)]
	#[Response(
		status: 200,
		description: 'Данные получены',
		content: [
			'id' => 1,
			'name' => 'test',
			'email' => 'test@example.com',
			'email_verified_at' => '2026-02-28T20:21:48.000000Z',
			'last_seen_at' => null,
			'created_at' => '2026-02-28T20:21:48.000000Z',
			'updated_at' => '2026-02-28T20:21:48.000000Z',
		]
	)]
	#[Response(status: 401, description: 'Пользователь не аутентифицирован')]
	public function show(): User
	{
		return Auth::user();
	}

	#[Endpoint(
		title: 'Обновить текущего пользователя',
		description: 'Обновляет данные текущего аутентифицированного пользователя.'
	)]
	#[BodyParam('name', required: false, type: 'string', example: 'John Doe', description: 'Имя пользователя')]
	#[BodyParam('email', required: false, type: 'string', example: 'john@example.com', description: 'Email адрес')]
	#[BodyParam('password', required: false, type: 'string', example: 'password', description: 'Новый пароль (мин. 8 символов)')]
	#[Response(
		status: 200,
		description: 'Данные обновлены',
		content: [
			'id' => 1,
			'name' => 'John Doe',
			'email' => 'john@example.com',
			'email_verified_at' => '2026-02-28T20:21:48.000000Z',
			'created_at' => '2026-02-28T20:21:48.000000Z',
			'updated_at' => '2026-02-28T20:25:00.000000Z',
		]
	)]
	#[Response(status: 401, description: 'Пользователь не аутентифицирован')]
	#[Response(status: 422, description: 'Ошибка валидации')]
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

	#[Endpoint(
		title: 'Удалить текущего пользователя',
		description: 'Безвозвратно удаляет аккаунт текущего пользователя.'
	)]
	#[Response(status: 200, description: 'Аккаунт удалён', content: [
		'message' => 'Аккаунт успешно удалён',
	])]
	#[Response(status: 401, description: 'Пользователь не аутентифицирован')]
	public function destroy(Request $request): JsonResponse
	{
		$user = $request->user();

		// Удаляем все токены
		$user->tokens()->delete();

		// Удаляем пользователя
		$user->delete();

		return response()->json([
			'message' => 'Аккаунт успешно удалён',
		]);
	}
}
