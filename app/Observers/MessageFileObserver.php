<?php

namespace App\Observers;

use App\Models\Message;
use App\Services\ChatFileService;

class MessageFileObserver
{
    public function __construct(
        private ChatFileService $fileService,
    ) {}

    /**
     * Handle the Message "deleted" event.
     */
    public function deleted(Message $message): void
    {
        // Удаляем все файлы, привязанные к сообщению
        $files = $message->files;

        foreach ($files as $file) {
            $this->fileService->deleteFile($file);
        }
    }
}
