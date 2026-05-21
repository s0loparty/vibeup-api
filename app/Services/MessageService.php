<?php

namespace App\Services;

use App\Http\Resources\Message\PublicMessageResource;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;

class MessageService
{
	public function context(Request $request, Chat $chat, int $messageId): array
	{
		if (!$chat->members()->where('user_id', $request->user()->id)->exists()) {
			abort(403, 'Not a member of this chat');
		}

		$limitOld = $request->integer('limit', 30);
		$prevCursor = $request->input('prev_cursor');

		$target = Message::where('chat_id', $chat->id)->findOrFail($messageId);

		// Если есть prev_cursor, значит нужно подгрузить более старые сообщения
		if ($prevCursor) {
			return $this->loadOlderMessages($chat, $target, $limitOld, $prevCursor);
		}

		// Первая загрузка контекста
		return $this->loadInitialContext($chat, $target, $limitOld);
	}

	private function loadInitialContext(Chat $chat, Message $target, int $limitOld): array
	{
		// 1. ВСЕ сообщения после целевого (новые) – без ограничения
		$afterMessages = Message::where('chat_id', $chat->id)
			->where('id', '>', $target->id)
			->orderBy('id', 'asc')
			->get();

		// 2. До $limitOld сообщений до целевого (старые)
		$beforeMessages = Message::where('chat_id', $chat->id)
			->where('id', '<', $target->id)
			->orderBy('id', 'desc')
			->limit($limitOld)
			->get()
			->reverse();

		// 3. Объединяем: старые + целевое + все новые
		$messages = $beforeMessages->concat([$target])->concat($afterMessages);

		// 4. Генерируем prev_cursor для подгрузки ещё более старых (если есть)
		$nextPrevCursor = null;
		if ($beforeMessages->count() === $limitOld && $beforeMessages->isNotEmpty()) {
			$oldestInBlock = $beforeMessages->first();
			$nextPrevCursor = $this->encodeCursor($oldestInBlock, false);
		}

		return [
			'data' => PublicMessageResource::collection($messages),
			'prev_cursor' => $nextPrevCursor,
			'next_cursor' => null,
			'target_message_id' => $target->id,
			'meta' => [
				'old_count' => $beforeMessages->count(),
				'new_count' => $afterMessages->count(),
				'has_more_old' => !is_null($nextPrevCursor),
			],
		];
	}

	private function loadOlderMessages(Chat $chat, Message $target, int $limitOld, string $cursorValue): array
	{
		$cursor = Cursor::fromEncoded($cursorValue);

		// Загружаем сообщения, которые старше чем позиция в курсоре
		$olderMessages = Message::where('chat_id', $chat->id)
			->where('id', '<', $target->id) // Только сообщения до целевого
			->where(function ($query) use ($cursor) {
				// Сортировка по created_at и id для курсорной пагинации
				$query->where('created_at', '<', $cursor->parameter('created_at'))
					->orWhere(function ($q) use ($cursor) {
						$q->where('created_at', $cursor->parameter('created_at'))
							->where('id', '<', $cursor->parameter('id'));
					});
			})
			->orderBy('id', 'desc')
			->limit($limitOld)
			->get()
			->reverse(); // Разворачиваем в хронологический порядок

		// Генерируем следующий курсор для дальнейшей подгрузки
		$nextPrevCursor = null;
		if ($olderMessages->count() === $limitOld && $olderMessages->isNotEmpty()) {
			$oldestInBlock = $olderMessages->first();
			$nextPrevCursor = $this->encodeCursor($oldestInBlock, false);
		}

		return [
			'data' => PublicMessageResource::collection($olderMessages),
			'prev_cursor' => $nextPrevCursor,
			'next_cursor' => null,
			'target_message_id' => $target->id,
			'meta' => [
				'old_count' => $olderMessages->count(),
				'new_count' => 0,
				'has_more_old' => !is_null($nextPrevCursor),
			],
		];
	}

	private function encodeCursor(Message $message, bool $pointsToNextItems = false): string
	{
		return (new Cursor([
			'created_at' => $message->created_at,
			'id' => $message->id,
			'_pointsToNextItems' => $pointsToNextItems,
		]))->encode();
	}
}
