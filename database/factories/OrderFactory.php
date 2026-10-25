<?php

namespace Database\Factories;

use App\Enums\AcCapacity;
use App\Enums\OfferStatus;
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

    /**
     * Record the offer to the assigned technician, as a real request would.
     */
    public function offered(OfferStatus $status = OfferStatus::Menunggu): static
    {
        return $this->afterCreating(function (Order $order) use ($status) {
            if ($order->technician_id !== null) {
                $order->offers()->create(['technician_id' => $order->technician_id, 'status' => $status]);
            }
        });
    }

    public function withEstimate(int $minimum = 100000, int $maximum = 150000): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::EstimasiMasuk,
            'estimate_min' => $minimum,
            'estimate_max' => $maximum,
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_window' => TimeWindow::Pagi,
            'response_deadline' => null,
        ])->offered(OfferStatus::Diterima);
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }
}
