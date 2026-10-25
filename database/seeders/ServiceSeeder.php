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
            ['slug' => 'cuci', 'name' => 'Cuci AC', 'description' => 'Bersihkan filter, evaporator, dan unit luar supaya dingin lagi.'],
            ['slug' => 'perbaikan', 'name' => 'Perbaikan', 'description' => 'AC tidak dingin, bocor, berisik, atau mati total.'],
            ['slug' => 'bongkar', 'name' => 'Bongkar-pasang', 'description' => 'Pindah unit ke ruangan atau rumah baru, termasuk instalasi.'],
            ['slug' => 'berkala', 'name' => 'Servis berkala', 'description' => 'Perawatan rutin dan cek freon agar AC awet.'],
        ];

        foreach ($services as $order => $service) {
            Service::updateOrCreate(['slug' => $service['slug']], [...$service, 'sort_order' => $order]);
        }
    }
}
