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
use OpenApi\Attributes as OA;

#[OA\Schema(
	schema: 'User',
	title: 'User',
	description: 'User model',
	required: ['id', 'username', 'email'],
	properties: [
		new OA\Property(property: 'id', type: 'integer', example: 1),
		new OA\Property(property: 'username', type: 'string', example: 'John Doe'),
		new OA\Property(property: 'bio', type: 'string', nullable: true, example: 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Consequuntur, labore!'),
		new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
		new OA\Property(property: 'email_verified_at', type: 'string', format: 'date-time', nullable: true),
		new OA\Property(property: 'last_seen_at', type: 'string', format: 'date-time', nullable: true),
		new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00Z'),
		new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00Z'),
	]
)]
#[ObservedBy([UserObserver::class])]
class User extends Authenticatable
{
	use HasApiTokens, HasFactory, Notifiable;

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var list<string>
	 */
	protected $fillable = [
		'username',
		'bio',
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
