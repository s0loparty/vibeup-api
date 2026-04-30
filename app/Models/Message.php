<?php

namespace App\Models;

use App\Enums\MessageType;
use App\Observers\MessageFileObserver;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;

#[ObservedBy([MessageFileObserver::class])]
class Message extends Model
{
	/** @use HasFactory<MessageFactory> */
	use HasFactory;

	protected $fillable = ['chat_id', 'sender_id', 'type', 'body', 'reply_to', 'edited_at'];

	protected $casts = [
		'type' => MessageType::class,
		'edited_at' => 'datetime:d.m.Y H:i',
		'created_at' => 'datetime:d.m.Y H:i',
		'updated_at' => 'datetime:d.m.Y H:i',
	];

	/**
	 * Разрешённые фильтры
	 */
	public function allowedFilters(): array
	{
		return [
			AllowedFilter::exact('type'),
			AllowedFilter::exact('sender_id'),
			AllowedFilter::scope('before'),
			AllowedFilter::scope('after'),
		];
	}

	/**
	 * Разрешённые включения (eager loading)
	 */
	public function allowedIncludes(): array
	{
		return [
			AllowedInclude::relationship('sender'),
			AllowedInclude::relationship('replyTo'),
			AllowedInclude::relationship('chat'),
		];
	}

	/**
	 * Разрешённые сортировки
	 */
	public function allowedSorts(): array
	{
		return [
			AllowedSort::field('id'),
			AllowedSort::field('created_at'),
			AllowedSort::field('updated_at'),
		];
	}

	/**
	 * Scope: сообщения до указанного ID (для курсорной пагинации)
	 */
	public function scopeBefore($query, int $value)
	{
		return $query->where('id', '<', $value);
	}

	/**
	 * Scope: сообщения после указанного ID (для курсорной пагинации)
	 */
	public function scopeAfter($query, int $value)
	{
		return $query->where('id', '>', $value);
	}

	public function chat(): BelongsTo
	{
		return $this->belongsTo(Chat::class);
	}

	public function sender(): BelongsTo
	{
		return $this->belongsTo(User::class, 'sender_id');
	}

	public function replyTo(): BelongsTo
	{
		return $this->belongsTo(Message::class, 'reply_to_id');
	}

	/**
	 * Файлы, привязанные к сообщению
	 */
	public function files(): HasMany
	{
		return $this->hasMany(ChatFile::class);
	}
}
