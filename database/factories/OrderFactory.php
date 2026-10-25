<?php

namespace Database\Factories;

use App\Enums\AcCapacity;
use App\Enums\OrderStatus;
use App\Enums\TimeWindow;
use App\Models\Order;
use App\Models\Service;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => User::factory(),
            'technician_id' => TechnicianProfile::factory(),
            'service_id' => Service::factory(),
            'ac_capacity' => AcCapacity::One,
            'ac_brand' => 'Daikin',
            'address' => fake()->streetAddress().', Malang',
            'latitude' => -7.9435,
            'longitude' => 112.6150,
            'preferred_date' => now()->addDay()->toDateString(),
            'time_window' => TimeWindow::Pagi,
            'warranty' => false,
            'warranty_fee' => 0,
            'status' => OrderStatus::MenungguRespons,
            'response_deadline' => now()->addMinutes((int) config('sejuk.response_minutes')),
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }
}
