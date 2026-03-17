<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create('messages', function (Blueprint $table) {
			$table->id();

			$table->foreignId('chat_id')
				->constrained('chats')
				->cascadeOnDelete();

			$table->foreignId('sender_id')
				->constrained('users')
				->cascadeOnDelete();

			// есть enums/MessageType
			$table->string('type');
			// body не всегда может быть текстом, напимер когда тип сообщения - image
			$table->text('body')->nullable();

			$table->foreignId('reply_to_id')
				->nullable()
				->constrained('messages')
				->nullOnDelete();

			$table->timestamp('edited_at')->nullable();
			$table->timestamps();

			// Критичный индекс для пагинации
			$table->index(['chat_id', 'id']);
			$table->index('sender_id');
			$table->index('created_at');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('messages');
	}
};
