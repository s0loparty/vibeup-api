<?php

namespace Database\Factories;

use App\Enums\ChatType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Chat>
 */
class ChatFactory extends Factory
{
	/**
	 * Define the model's default state.
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array
	{
		return [
			'title' => fake()->company(),
			'type' => ChatType::GROUP,
			'created_by' => User::factory(),
		];
	}

	public function private(): static
	{
		return $this->state(fn (array $attributes) => [
			'title' => null,
			'type' => ChatType::PRIVATE,
		]);
	}
}
