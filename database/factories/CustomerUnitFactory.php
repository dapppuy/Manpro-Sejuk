<?php

namespace Database\Factories;

use App\Models\CustomerUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerUnit>
 */
class CustomerUnitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->customer(),
            'name' => 'AC '.fake()->randomElement(['kamar depan', 'ruang tamu', 'kamar tidur']),
            'room_label' => null,
            'is_kos_unit' => false,
            'brand' => fake()->randomElement(['Daikin', 'Sharp', 'Panasonic', 'LG']),
            'capacity_pk' => fake()->randomElement(['0.5', '1', '1.5']),
            'ac_type' => 'split',
        ];
    }

    public function kos(string $room = 'Kamar A-03'): static
    {
        return $this->state(fn (array $attributes) => [
            'is_kos_unit' => true,
            'room_label' => $room,
        ]);
    }
}
