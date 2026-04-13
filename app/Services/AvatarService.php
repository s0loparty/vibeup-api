<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AvatarService
{
    private const COLLECTION_NAME = 'avatars';

    /**
     * Загрузить или заменить аватарку пользователя
     */
    public function uploadAvatar(User $user, UploadedFile $file): Media
    {
        // Используем публичный диск, чтобы аватарки были доступны без подписи
        return $user->addMedia($file)
            ->toMediaCollection(self::COLLECTION_NAME, 'minio-public');
    }

    /**
     * Удалить аватарку пользователя
     */
    public function deleteAvatar(User $user): void
    {
        $user->clearMediaCollection(self::COLLECTION_NAME);
    }

    /**
     * Получить URL аватарки (оптимизированная версия)
     */
    public function getAvatarUrl(User $user): ?string
    {
        $media = $user->getFirstMedia(self::COLLECTION_NAME);

        // Возвращаем оптимизированную версию (WebP), если она есть
        // Если конверсия еще не выполнена (в очереди), вернется оригинал
        return $media?->getFullUrl('optimized') ?? $media?->getFullUrl();
    }

    /**
     * Получить URL thumbnail аватарки
     */
    public function getThumbnailUrl(User $user): ?string
    {
        $media = $user->getFirstMedia(self::COLLECTION_NAME);

        return $media?->getFullUrl('thumbnail');
    }
}
