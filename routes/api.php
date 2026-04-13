<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AvatarController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\ChatFileController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\MessageController;
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
        Route::post('/user/avatar', [AvatarController::class, 'upload'])->name('user.avatar.upload');
        Route::delete('/user/avatar', [AvatarController::class, 'destroy'])->name('user.avatar.destroy');
        Route::get('/users/{user}/avatar', [AvatarController::class, 'show'])->name('users.avatar.show');

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

        // Message routes nested within chats
        Route::apiResource('chats.messages', MessageController::class)->only(['index', 'show', 'store', 'update', 'destroy'])->shallow();
    });
});
