<?php

namespace Database\Factories;

use App\Enums\TechnicianBadge;
use App\Enums\TimeWindow;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TechnicianProfile>
 */
class TechnicianProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $business = fake()->unique()->company().' AC';

        return [
            'user_id' => User::factory()->state(['role' => UserRole::Technician]),
            'business_name' => $business,
            'slug' => Str::slug($business).'-'.Str::lower(Str::random(4)),
            'bio' => fake()->paragraph(),
            'years_experience' => fake()->numberBetween(1, 15),
            'jobs_count' => fake()->numberBetween(0, 500),
            'service_radius_km' => 10,
            'base_area' => 'Lowokwaru',
            'latitude' => -7.9666,
            'longitude' => 112.6326,
            'badge' => TechnicianBadge::Terdaftar,
            'verification_status' => VerificationStatus::Approved,
            'verified_at' => now(),
            'is_active' => true,
            'is_accepting_orders' => true,
            'is_promoted' => false,
            'avg_response_minutes' => 5,
            'rating_avg' => 4.5,
            'reviews_count' => 0,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => VerificationStatus::Pending,
            'verified_at' => null,
            'is_accepting_orders' => false,
        ]);
    }

    public function badge(TechnicianBadge $badge): static
    {
        return $this->state(fn (array $attributes) => [
            'badge' => $badge,
        ]);
    }

    public function promoted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_promoted' => true,
        ]);
    }

    public function at(float $latitude, float $longitude): static
    {
        return $this->state(fn (array $attributes) => [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    /**
     * Available every day in every time window.
     */
    public function alwaysAvailable(): static
    {
        return $this->afterCreating(function (TechnicianProfile $technician) {
            foreach (range(0, 6) as $day) {
                foreach (TimeWindow::cases() as $window) {
                    $technician->availabilities()->create([
                        'day_of_week' => $day,
                        'time_window' => $window,
                        'is_available' => true,
                    ]);
                }
            }
        });
    }
}
