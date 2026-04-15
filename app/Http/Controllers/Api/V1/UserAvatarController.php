<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AvatarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class UserAvatarController extends Controller
{
	public function __construct(
		private AvatarService $avatarService,
	) {
	}

	#[OA\Get(
		path: '/api/v1/users/{user}/avatar',
		operationId: 'users.avatars.show',
		summary: 'Получить аватарку пользователя',
		description: 'Возвращает URL оригинальной аватарки и thumbnail',
		tags: ['Аватарки'],
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\Parameter(
				name: 'user',
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
				content: new OA\JsonContent(
					properties: [
						new OA\Property(property: 'original', type: 'string', nullable: true, example: 'https://minio.example.com/bucket/1/avatar.jpg'),
						new OA\Property(property: 'thumbnail', type: 'string', nullable: true, example: 'https://minio.example.com/bucket/1/avatar_thumb.webp'),
					]
				)
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

	#[OA\Post(
		path: '/api/v1/users/{user}/avatar',
		operationId: 'users.avatars.store',
		summary: 'Загрузить или заменить аватарку',
		description: 'Загружает новую аватарку. Старая аватарка автоматически удаляется.',
		tags: ['Аватарки'],
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\Parameter(
				name: 'user',
				in: 'path',
				required: true,
				description: 'ID пользователя (только для себя)',
				schema: new OA\Schema(type: 'integer')
			),
		],
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
				content: new OA\JsonContent(
					properties: [
						new OA\Property(property: 'original', type: 'string', example: 'https://minio.example.com/bucket/1/avatar.jpg'),
						new OA\Property(property: 'thumbnail', type: 'string', example: 'https://minio.example.com/bucket/1/avatar_thumb.webp'),
					]
				)
			),
			new OA\Response(
				response: 400,
				description: 'Ошибка валидации',
				content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
			),
			new OA\Response(
				response: 403,
				description: 'Нет доступа',
				content: new OA\JsonContent(
					properties: [
						new OA\Property(property: 'message', type: 'string', example: 'You can only manage your own avatar'),
					]
				)
			),
		]
	)]
	public function store(Request $request, User $user): JsonResponse
	{
		abort_unless($request->user()->is($user), 403, 'You can only manage your own avatar');

		$request->validate([
			'avatar' => ['required', 'file', 'mimes:jpeg,png,webp', 'max:2048'],
		]);

		$media = $this->avatarService->uploadAvatar($user, $request->file('avatar'));

		return response()->json([
			'original' => $media->getFullUrl(),
			'thumbnail' => $media->getFullUrl('thumbnail'),
		]);
	}

	#[OA\Delete(
		path: '/api/v1/users/{user}/avatar',
		operationId: 'users.avatars.destroy',
		summary: 'Удалить аватарку',
		description: 'Удаляет текущую аватарку пользователя',
		tags: ['Аватарки'],
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\Parameter(
				name: 'user',
				in: 'path',
				required: true,
				description: 'ID пользователя (только для себя)',
				schema: new OA\Schema(type: 'integer')
			),
		],
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
				response: 403,
				description: 'Нет доступа',
				content: new OA\JsonContent(
					properties: [
						new OA\Property(property: 'message', type: 'string', example: 'You can only manage your own avatar'),
					]
				)
			),
		]
	)]
	public function destroy(Request $request, User $user): JsonResponse
	{
		abort_unless($request->user()->is($user), 403, 'You can only manage your own avatar');

		$this->avatarService->deleteAvatar($user);

		return response()->json([
			'message' => 'Аватарка успешно удалена',
		]);
	}
}
