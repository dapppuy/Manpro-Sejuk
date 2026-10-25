<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ServiceSeeder::class,
            TechnicianSeeder::class,
        ]);

        $customer = User::factory()->create([
            'name' => 'Dimas Pratama',
            'email' => 'dimas@sejuk.test',
            'phone' => '081234567890',
            'phone_verified_at' => now(),
        ]);

        $customer->addresses()->createMany([
            [
                'label' => 'Kos Melati',
                'address' => 'Jl. Kertoraharjo, Ketawanggede, Lowokwaru',
                'detail' => 'Kos Melati, kamar 12',
                'latitude' => TechnicianSeeder::ORIGIN[0],
                'longitude' => TechnicianSeeder::ORIGIN[1],
                'is_primary' => true,
            ],
            [
                'label' => 'Rumah orang tua',
                'address' => 'Jl. Danau Toba No. 5, Sawojajar, Malang',
                'detail' => null,
                'latitude' => -7.9748,
                'longitude' => 112.6598,
                'is_primary' => false,
            ],
        ]);

        User::factory()->admin()->create([
            'name' => 'Admin Sejuk',
            'email' => 'admin@sejuk.test',
        ]);
    }
}
