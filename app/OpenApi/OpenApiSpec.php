<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\OpenApi(
	openapi: '3.0.0',
	info: new OA\Info(
		version: '1.0.0',
		title: 'Vibe API',
		description: 'API documentation for Vibe application'
	),
	servers: [
		new OA\Server(url: 'http://localhost:80', description: 'Local development server'),
	],
	components: new OA\Components(
		schemas: [
			// Auth Token Schema
			new OA\Schema(
				schema: 'AuthToken',
				title: 'AuthToken',
				description: 'Токен аутентификации',
				required: ['token', 'type'],
				properties: [
					new OA\Property(property: 'token', type: 'string', example: '1|abc123xyz456'),
					new OA\Property(property: 'type', type: 'string', example: 'Bearer'),
				]
			),

			// Chat Schema
			new OA\Schema(
				schema: 'Chat',
				title: 'Chat',
				description: 'Чат',
				required: ['id', 'type', 'created_by'],
				properties: [
					new OA\Property(property: 'id', type: 'integer', example: 1),
					new OA\Property(property: 'type', type: 'string', enum: ['private', 'group'], example: 'private'),
					new OA\Property(property: 'title', type: 'string', nullable: true, example: 'Мой чат'),
					new OA\Property(property: 'created_by', type: 'integer', example: 1),
					new OA\Property(property: 'last_message_id', type: 'integer', nullable: true),
					new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
					new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
				]
			),

			// Chat Member Schema
			new OA\Schema(
				schema: 'ChatMember',
				title: 'ChatMember',
				description: 'Участник чата',
				required: ['user_id', 'chat_id', 'role'],
				properties: [
					new OA\Property(property: 'user_id', type: 'integer', example: 1),
					new OA\Property(property: 'chat_id', type: 'integer', example: 1),
					new OA\Property(property: 'role', type: 'string', enum: ['owner', 'moderator', 'member'], example: 'owner'),
					new OA\Property(property: 'last_read_message_id', type: 'integer', nullable: true),
					new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
					new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
					new OA\Property(property: 'user', ref: '#/components/schemas/User', nullable: true),
				]
			),

			// Message Schema
			new OA\Schema(
				schema: 'Message',
				title: 'Message',
				description: 'Сообщение в чате',
				required: ['id', 'chat_id', 'sender_id', 'type', 'body'],
				properties: [
					new OA\Property(property: 'id', type: 'integer', example: 1),
					new OA\Property(property: 'chat_id', type: 'integer', example: 1),
					new OA\Property(property: 'sender_id', type: 'integer', example: 1),
					new OA\Property(property: 'type', type: 'string', enum: ['text', 'image', 'video', 'audio', 'file'], example: 'text'),
					new OA\Property(property: 'body', type: 'string', example: 'Привет, как дела?'),
					new OA\Property(property: 'reply_to_id', type: 'integer', nullable: true),
					new OA\Property(property: 'edited_at', type: 'string', format: 'date-time', nullable: true),
					new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
					new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
					new OA\Property(property: 'sender', ref: '#/components/schemas/User', nullable: true),
					new OA\Property(property: 'reply_to', ref: '#/components/schemas/Message', nullable: true),
				]
			),

			// Cursor Pagination Meta
			new OA\Schema(
				schema: 'CursorPaginationMeta',
				title: 'CursorPaginationMeta',
				description: 'Метаданные курсорной пагинации',
				properties: [
					new OA\Property(property: 'path', type: 'string', format: 'uri', example: 'http://localhost/api/v1/chats'),
					new OA\Property(property: 'per_page', type: 'integer', example: 30),
					new OA\Property(property: 'next_cursor', type: 'string', nullable: true, example: 'eyJpZCI6NTN9'),
					new OA\Property(property: 'next_page_url', type: 'string', format: 'uri', nullable: true),
					new OA\Property(property: 'prev_cursor', type: 'string', nullable: true),
					new OA\Property(property: 'prev_page_url', type: 'string', format: 'uri', nullable: true),
				]
			),

			// Error Response Schema
			new OA\Schema(
				schema: 'Error',
				title: 'Error',
				description: 'Ошибка',
				required: ['message'],
				properties: [
					new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
				]
			),

			// Validation Error Schema
			new OA\Schema(
				schema: 'ValidationError',
				title: 'ValidationError',
				description: 'Ошибка валидации',
				required: ['message', 'errors'],
				properties: [
					new OA\Property(property: 'message', type: 'string', example: 'The email field is required. (and 1 more error)'),
					new OA\Property(property: 'errors', type: 'object',
						additionalProperties: new OA\AdditionalProperties(
							type: 'array',
							items: new OA\Items(type: 'string')
						),
						example: ['email' => ['The email field is required.']]
					),
				]
			),
		],
		securitySchemes: [
			new OA\SecurityScheme(
				securityScheme: 'bearerAuth',
				type: 'http',
				description: 'Введите токен в формате: Bearer <token>',
				scheme: 'bearer',
				bearerFormat: 'JWT'
			),
		]
	),
	tags: [
		new OA\Tag(name: 'Авторизация', description: 'Операции аутентификации и регистрации пользователей'),
		new OA\Tag(name: 'Текущий пользователь', description: 'Операции управления данными текущего пользователя'),
		new OA\Tag(name: 'Чаты', description: 'Операции управления чатами'),
		new OA\Tag(name: 'Сообщения', description: 'Операции управления сообщениями в чатах'),
	]
)]
class OpenApiSpec
{
}
