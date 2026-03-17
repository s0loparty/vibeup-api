<?php

namespace App\Enums;

enum ChatMemberRole: string
{
	case MEMBER = 'member';
	case OWNER = 'owner';
	case MODERATOR = 'moderator';
}
