<?php

namespace App\Actions\Chat;

use App\Models\Chat;
use App\Models\User;
use App\Enums\ChatType;

final class FindExistingPrivateChatAction
{
	public function execute(User $user, int $participantId): ?Chat
	{
		return Chat::where('type', ChatType::PRIVATE)
			->whereHas('members', fn($q) => $q->where('user_id', $user->id))
			->whereHas('members', fn($q) => $q->where('user_id', $participantId))
			->first();
	}
}
