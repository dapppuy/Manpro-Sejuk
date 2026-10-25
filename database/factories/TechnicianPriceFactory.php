<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\TechnicianPrice;
use App\Models\TechnicianProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TechnicianPrice>
 */
class TechnicianPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $minimum = fake()->numberBetween(5, 20) * 5000;

        return [
            'technician_id' => TechnicianProfile::factory(),
            'service_id' => Service::factory(),
            'note' => '0,5–1 PK',
            'price_min' => $minimum,
            'price_max' => $minimum + 25000,
        ];
    }
}
