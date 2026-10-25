<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            ['slug' => 'cuci', 'name' => 'Cuci AC', 'description' => 'Bersihkan filter, evaporator & unit luar'],
            ['slug' => 'perbaikan', 'name' => 'Perbaikan', 'description' => 'AC tidak dingin, bocor, berisik, mati'],
            ['slug' => 'bongkar', 'name' => 'Bongkar-pasang', 'description' => 'Pindah kamar atau pasang unit baru'],
            ['slug' => 'berkala', 'name' => 'Servis berkala', 'description' => 'Perawatan rutin tiap 3–6 bulan'],
        ];

        foreach ($services as $order => $service) {
            Service::updateOrCreate(['slug' => $service['slug']], [...$service, 'sort_order' => $order]);
        }
    }
}
