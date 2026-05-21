<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\ChatFileController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\UserAvatarController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
	// Authentication routes
	Route::controller(AuthController::class)->prefix('auth')->name('auth.')->group(function () {
		Route::post('/register', 'register')->name('register');
		Route::post('/login', 'login')->name('login');
		Route::post('/logout', 'logout')->middleware('auth:sanctum')->name('logout');
	});

	Route::middleware('auth:sanctum')->group(function () {
		// Avatar routes
		Route::prefix('users/{user}')->group(function () {
			Route::get('/avatar', [UserAvatarController::class, 'show'])->name('users.avatars.show');
			Route::post('/avatar', [UserAvatarController::class, 'store'])->name('users.avatars.store');
			Route::delete('/avatar', [UserAvatarController::class, 'destroy'])->name('users.avatars.destroy');
		});

		// Текущий пользователь (без {user} в URL)
		Route::get('/user', [CurrentUserController::class, 'show'])->name('user.show');
		Route::put('/user', [CurrentUserController::class, 'update'])->name('user.update');
		Route::delete('/user', [CurrentUserController::class, 'destroy'])->name('user.destroy');

		// Chat routes
		Route::apiResource('chats', ChatController::class)->only(['index', 'show', 'store', 'update', 'destroy']);

		// Chat files routes
		Route::prefix('chats/{chat}')->group(function () {
			Route::post('/files', [ChatFileController::class, 'store'])->name('chats.files.store');
			Route::get('/files/{file}/download', [ChatFileController::class, 'download'])->name('chats.files.download');
			Route::delete('/files/{file}', [ChatFileController::class, 'destroy'])->name('chats.files.destroy');
		});

		// Message route context
		Route::get('chats/{chat}/messages/context/{message}', [MessageController::class, 'context'])
			->name('chats.messages.context');

		// Message routes nested within chats
		Route::apiResource('chats.messages', MessageController::class)->only(['index', 'show', 'store', 'update', 'destroy'])->shallow();
	});
});
