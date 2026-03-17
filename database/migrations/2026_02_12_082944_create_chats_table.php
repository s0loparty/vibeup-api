<?php

use App\Enums\ChatType;
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
		Schema::create('chats', function (Blueprint $table) {
			$table->id();

			$table->string('type')->default(ChatType::PRIVATE ->value);
			$table->string('title')->nullable();

			$table->foreignId('created_by')
				->constrained('users')
				->cascadeOnDelete();

			$table->timestamps();

			$table->index('type');
			$table->index('created_by');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('chats');
	}
};
