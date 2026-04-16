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
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Image\Enums\Fit;

#[ObservedBy([UserObserver::class])]
class User extends Authenticatable implements HasMedia
{
	use HasApiTokens, HasFactory, InteractsWithMedia, Notifiable;

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
		'media',
		'pivot',
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
			'created_at' => 'datetime:d.m.Y H:i',
			'updated_at' => 'datetime:d.m.Y H:i',
			'email_verified_at' => 'datetime:d.m.Y H:i',
			'last_seen_at' => 'datetime:d.m.Y H:i',
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

	/**
	 * Регистрация media collections
	 */
	public function registerMediaCollections(): void
	{
		$this->addMediaCollection('avatars')
			->singleFile()
			->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
			->registerMediaConversions(function () {
				$this->addMediaConversion('optimized')
					->fit(Fit::Max, 512, 512)
					->quality(85)
					->optimize()
					->format('webp')
					->nonQueued();

				// Thumbnail 120x120
				$this->addMediaConversion('thumbnail')
					->fit(Fit::Crop, 120, 120)
					->quality(80)
					->sharpen(10)
					->optimize()
					->format('webp')
					->nonQueued();
			});
	}

	/**
	 * Аксессор для получения URL аватарки (оригинал + thumbnail)
	 */
	public function getAvatarAttribute(): ?array
	{
		$media = $this->getFirstMedia('avatars');

		if ($media) {
			return [
				'original' => $media?->getFullUrl(),
				'thumbnail' => $media?->getFullUrl('thumbnail'),
			];
		}

		return null;
	}
}
