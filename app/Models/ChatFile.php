<?php

namespace App\Models;

use Database\Factories\ChatFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatFile extends Model
{
    /** @use HasFactory<ChatFileFactory> */
    use HasFactory;

    protected $fillable = [
        'chat_id',
        'user_id',
        'message_id',
        'original_name',
        'mime_type',
        'size',
        's3_key',
        's3_url',
    ];

    protected $casts = [
        'size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Чат, к которому принадлежит файл
     */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    /**
     * Пользователь, загрузивший файл
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Сообщение, к которому привязан файл
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Scope: общий размер файлов в чате
     */
    public function scopeTotalSizeForChat($query, int $chatId): int
    {
        return $query->where('chat_id', $chatId)->sum('size');
    }

    /**
     * Scope: общий размер файлов пользователя
     */
    public function scopeTotalSizeForUser($query, int $userId): int
    {
        return $query->where('user_id', $userId)->sum('size');
    }

    /**
     * Проверка, является ли файл изображением
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
