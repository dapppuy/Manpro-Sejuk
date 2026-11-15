<?php

namespace Database\Factories;

use App\Models\CustomerUnit;
use App\Models\UnitServiceLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitServiceLog>
 */
class UnitServiceLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_unit_id' => CustomerUnit::factory(),
            'service_date' => now()->subMonth()->toDateString(),
            'service_type' => 'Cuci AC',
            'inspection_findings' => 'Filter berdebu, evaporator kotor.',
            'actions_taken' => 'Cuci indoor dan outdoor, bersihkan filter.',
            'next_recommended_service_date' => now()->addMonths(2)->toDateString(),
        ];
    }
}
