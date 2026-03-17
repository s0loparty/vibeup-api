<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::prefix('v1')->name('api.v1.')->group(function () {
	// Authentication routes
	Route::controller(AuthController::class)->prefix('auth')->name('auth.')->group(function () {
		Route::post('/register', 'register')->name('register');
		Route::post('/login', 'login')->name('login');
		Route::post('/logout', 'logout')->middleware('auth:sanctum')->name('logout');
	});

	Route::middleware('auth:sanctum')->group(function () {
		// Текущий пользователь (без {user} в URL)
		Route::get('/user', [CurrentUserController::class, 'show'])->name('user.show');
		Route::put('/user', [CurrentUserController::class, 'update'])->name('user.update');
		Route::delete('/user', [CurrentUserController::class, 'destroy'])->name('user.destroy');

		// Chat routes
		Route::apiResource('chats', ChatController::class)->only(['index', 'show']);

		// Message routes nested within chats
		Route::apiResource('chats.messages', MessageController::class)->only(['index', 'show', 'store', 'update', 'destroy'])->shallow();
	});
});
