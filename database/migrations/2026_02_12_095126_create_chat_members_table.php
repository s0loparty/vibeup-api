<?php

use App\Enums\ChatMemberRole;
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
		Schema::create('chat_members', function (Blueprint $table) {
			$table->id();

			$table->foreignId('chat_id')
				->constrained('chats')
				->cascadeOnDelete();

			$table->foreignId('user_id')
				->constrained('users')
				->cascadeOnDelete();

			$table->string('role')->default(ChatMemberRole::MEMBER->value);
			$table->timestamp('joined_at')->useCurrent();

			$table->timestamps();

			$table->unique(['chat_id', 'user_id']);
			$table->index('user_id');
			$table->index('chat_id');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('chat_members');
	}
};
