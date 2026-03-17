<?php

namespace App\Models;

use App\Enums\ChatType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;

class Chat extends Model
{
	/** @use HasFactory<\Database\Factories\ChatFactory> */
	use HasFactory;

	protected $casts = [
		'type' => ChatType::class,
	];

	protected $fillable = ['type', 'title', 'created_by', 'last_message_id'];

	/**
	 * Разрешённые фильтры
	 */
	public function allowedFilters(): array
	{
		return [
			AllowedFilter::exact('type'),
			AllowedFilter::scope('with_user'),
		];
	}

	/**
	 * Разрешённые включения (eager loading)
	 */
	public function allowedIncludes(): array
	{
		return [
			AllowedInclude::relationship('members'),
			AllowedInclude::relationship('lastMessage'),
			AllowedInclude::relationship('creator'),
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
			AllowedSort::field('last_message_id'),
		];
	}

	/**
	 * Scope: чаты с указанным пользователем (для private)
	 */
	public function scopeWithUser($query, int $userId)
	{
		return $query->whereHas('members', fn ($q) => $q->where('user_id', $userId));
	}

	public function members(): BelongsToMany
	{
		return $this->belongsToMany(User::class, 'chat_members')
			->withPivot(['role', 'last_read_message_id'])
			->withTimestamps();
	}

	public function messages(): HasMany
	{
		return $this->hasMany(Message::class);
	}

	public function creator(): BelongsTo
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	public function lastMessage(): BelongsTo
	{
		return $this->belongsTo(Message::class, 'last_message_id');
	}

	public function isPrivate(): bool
	{
		return $this->type === ChatType::PRIVATE;
	}

	public function getOtherUser(int $currentUserId): ?User
	{
		if ($this->type !== ChatType::PRIVATE) {
			return null;
		}

		return $this->members->firstWhere('id', '!=', $currentUserId);
	}
}
