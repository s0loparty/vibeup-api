<?php

namespace App\Models;

use App\Enums\MessageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;

class Message extends Model
{
	/** @use HasFactory<\Database\Factories\MessageFactory> */
	use HasFactory;

	protected $fillable = ['chat_id', 'sender_id', 'type', 'body', 'reply_to', 'edited_at'];

	protected $casts = [
		'type' => MessageType::class,
		'edited_at' => 'datetime',
		'created_at' => 'datetime',
		'updated_at' => 'datetime',
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
}
