<?php

namespace App\Models;

use App\Observers\UserObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[ObservedBy([UserObserver::class])]
class User extends Authenticatable
{
	use HasFactory, Notifiable, HasApiTokens;

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var list<string>
	 */
	protected $fillable = [
		'name',
		'email',
		'password',
		'last_seen_at',
	];

	/**
	 * The attributes that should be hidden for serialization.
	 *
	 * @var list<string>
	 */
	protected $hidden = [
		'password',
		'remember_token',
	];

	/**
	 * Get the attributes that should be cast.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'email_verified_at' => 'datetime',
			'last_seen_at' => 'datetime',
			'password' => 'hashed',
		];
	}

	/**
	 * Чаты, где пользователь является участником (через chat_members)
	 */
	public function chats(): BelongsToMany
	{
		return $this->belongsToMany(Chat::class, 'chat_members')
			->withPivot(['role', 'last_read_message_id'])
			->withTimestamps();
	}

	/**
	 * Чаты, которые пользователь создал
	 */
	public function createdChats(): HasMany
	{
		return $this->hasMany(Chat::class, 'created_by');
	}

	public function messages(): HasMany
	{
		return $this->hasMany(Message::class, 'sender_id');
	}
}
