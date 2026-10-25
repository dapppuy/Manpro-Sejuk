<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Review;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => null,
            'customer_id' => User::factory()->state(['role' => UserRole::Customer]),
            'technician_id' => TechnicianProfile::factory(),
            'rating' => fake()->numberBetween(4, 5),
            'comment' => fake()->sentence(),
            'is_hidden' => false,
        ];
    }
}
