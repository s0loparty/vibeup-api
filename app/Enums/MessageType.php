<?php

namespace App\Enums;

enum MessageType: string
{
	case TEXT = 'text';
	case SYSTEM = 'system';
	case IMAGE = 'image';
	case VIDEO = 'video';
	case AUDIO = 'audio';
	case FILE = 'file';
}
