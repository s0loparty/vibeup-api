<?php

namespace App\Models;

use App\Enums\ChatMemberRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMember extends Model
{
	/** @use HasFactory<\Database\Factories\ChatMemberFactory> */
	use HasFactory;

	protected $fillable = ['chat_id', 'user_id', 'role', 'joined_at'];

	protected $casts = [
		'role' => ChatMemberRole::class,
	];

	public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}

	public function chat(): BelongsTo
	{
		return $this->belongsTo(Chat::class);
	}

	public function lastReadMessage(): BelongsTo
	{
		return $this->belongsTo(Message::class, 'last_read_message_id');
	}
}
