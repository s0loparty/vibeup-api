<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\ChatFile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatFileService
{
    // Константы для квот (в байтах)
    public const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100MB

    public const MAX_CHAT_SIZE = 500 * 1024 * 1024; // 500MB

    public const MAX_USER_QUOTA = 1024 * 1024 * 1024; // 1GB

    public const TEMPORARY_URL_LIFETIME = 5; // минут

    /**
     * Загрузить файл в чат (с потоковой передачей и имитацией транзакции)
     *
     * @throws \Exception
     */
    public function uploadFile(Chat $chat, User $user, UploadedFile $file): ChatFile
    {
        $this->checkUserQuota($user, $file->getSize());
        $this->checkChatQuota($chat, $file->getSize());

        $path = 'chat_files/'.$chat->id;
        $fileName = Str::uuid().'.'.$file->getClientOriginalExtension();
        $s3Key = null;

        try {
            // 1. Потоковая загрузка в S3 через stream (экономит память)
            $stream = fopen($file->getPathname(), 'r');
            Storage::disk('minio')->put($path.'/'.$fileName, $stream, 'private');
            if (is_resource($stream)) {
                fclose($stream);
            }

            $s3Key = $path.'/'.$fileName;

            // 2. Запись в БД (в транзакции)
            return DB::transaction(function () use ($chat, $user, $file, $s3Key) {
                return ChatFile::create([
                    'chat_id' => $chat->id,
                    'user_id' => $user->id,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    's3_key' => $s3Key,
                ]);
            });
        } catch (\Exception $e) {
            // ROLLBACK: Если запись в БД не удалась, удаляем файл из S3, чтобы не было мусора
            if ($s3Key) {
                Storage::disk('minio')->delete($s3Key);
            }
            throw $e;
        }
    }

    /**
     * Сгенерировать временную ссылку для скачивания
     */
    public function generateTemporaryUrl(ChatFile $chatFile, ?int $minutes = null): string
    {
        $expiration = $minutes ?? self::TEMPORARY_URL_LIFETIME;

        return Storage::disk('minio')->temporaryUrl(
            $chatFile->s3_key,
            Carbon::now()->addMinutes($expiration)
        );
    }

    /**
     * Удалить файл из S3 и БД
     */
    public function deleteFile(ChatFile $chatFile): void
    {
        // Сначала удаляем из БД
        $chatFile->delete();
        // Потом из S3 (если БД упала — файл останется в S3, что безопаснее, чем потеря данных)
        Storage::disk('minio')->delete($chatFile->s3_key);
    }

    /**
     * Удалить все файлы чата
     */
    public function deleteAllChatFiles(Chat $chat): void
    {
        $files = ChatFile::where('chat_id', $chat->id)->get();

        foreach ($files as $file) {
            $this->deleteFile($file);
        }
    }

    /**
     * Привязать файл к сообщению
     */
    public function attachToMessage(ChatFile $chatFile, int $messageId): void
    {
        $chatFile->update(['message_id' => $messageId]);
    }

    /**
     * Проверка квоты пользователя
     */
    private function checkUserQuota(User $user, int $newFileSize): void
    {
        $currentSize = ChatFile::where('user_id', $user->id)->sum('size');
        if (($currentSize + $newFileSize) > self::MAX_USER_QUOTA) {
            throw new \Exception('Превышена квота дискового пространства (1 ГБ). Удалите старые файлы.');
        }
    }

    /**
     * Проверка квоты чата
     */
    private function checkChatQuota(Chat $chat, int $newFileSize): void
    {
        $currentSize = ChatFile::where('chat_id', $chat->id)->sum('size');
        if (($currentSize + $newFileSize) > self::MAX_CHAT_SIZE) {
            throw new \Exception('Превышен лимит хранилища чата (500 МБ).');
        }
    }
}
