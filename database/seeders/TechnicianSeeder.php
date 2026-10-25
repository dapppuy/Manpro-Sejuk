<?php

namespace Database\Seeders;

use App\Enums\TechnicianBadge;
use App\Enums\TimeWindow;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Service;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TechnicianSeeder extends Seeder
{
    /**
     * Sample price rows per service slug: [note, min, max].
     *
     * @var array<string, list<array{0: string, 1: int, 2: int}>>
     */
    private const PRICES = [
        'cuci' => [['0,5–1 PK', 65000, 80000], ['1,5–2 PK', 85000, 100000]],
        'perbaikan' => [['Cek + perbaikan ringan', 100000, 175000], ['Isi freon R32', 200000, 300000]],
        'bongkar' => [['Bongkar + pasang ulang', 300000, 450000]],
        'berkala' => [['Per unit, per kunjungan', 75000, 90000]],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = Service::all()->keyBy('slug');

        foreach ($this->technicians() as $index => $data) {
            $user = User::factory()->create([
                'name' => $data['name'],
                'email' => Str::lower(Str::before($data['name'], ' ')).'@sejuk.test',
                'phone' => $data['phone'],
                'phone_verified_at' => now(),
                'role' => UserRole::Technician,
            ]);

            $technician = $user->technicianProfile()->create([
                'business_name' => $data['business'],
                'slug' => Str::slug($data['business']),
                'bio' => $data['bio'],
                'years_experience' => $data['years'],
                'jobs_count' => $data['jobs'],
                'service_radius_km' => $data['radius'],
                'base_area' => $data['areas'][0],
                'latitude' => $data['lat'],
                'longitude' => $data['lng'],
                'badge' => $data['badge'],
                'verification_status' => VerificationStatus::Approved,
                'verified_at' => now()->subMonths(6 - $index),
                'is_active' => true,
                'is_accepting_orders' => true,
                'is_promoted' => $data['promoted'],
                'avg_response_minutes' => $data['response'],
                'rating_avg' => 0,
                'reviews_count' => 0,
            ]);

            foreach ($data['services'] as $slug) {
                $technician->services()->attach($services[$slug]);

                foreach (self::PRICES[$slug] as [$note, $min, $max]) {
                    $technician->prices()->create([
                        'service_id' => $services[$slug]->id,
                        'note' => $note,
                        'price_min' => $min + $data['price_offset'],
                        'price_max' => $max + $data['price_offset'],
                    ]);
                }
            }

            $technician->serviceAreas()->createMany(array_map(fn (string $area) => ['area_name' => $area], $data['areas']));
            $technician->brands()->createMany(array_map(fn (string $brand) => ['brand' => $brand], $data['brands']));

            foreach ($data['days'] as $day) {
                foreach ($data['windows'] as $window) {
                    $technician->availabilities()->create(['day_of_week' => $day, 'time_window' => $window, 'is_available' => true]);
                }
            }

            $this->seedReviews($technician, $data['reviews']);
        }
    }

    /**
     * @param  list<array{0: string, 1: int, 2: string}>  $reviews
     */
    private function seedReviews(TechnicianProfile $technician, array $reviews): void
    {
        foreach ($reviews as $daysAgo => [$name, $rating, $comment]) {
            $customer = User::firstOrCreate(
                ['email' => Str::slug($name, '.').'@pelanggan.test'],
                ['name' => $name, 'password' => 'password', 'role' => UserRole::Customer, 'city' => 'Malang'],
            );

            $technician->reviews()->forceCreate([
                'customer_id' => $customer->id,
                'rating' => $rating,
                'comment' => $comment,
                'created_at' => now()->subDays(($daysAgo + 1) * 6),
            ]);
        }

        $technician->update([
            'rating_avg' => round((float) $technician->reviews()->avg('rating'), 1),
            'reviews_count' => $technician->reviews()->count(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function technicians(): array
    {
        $everyDay = range(0, 6);
        $allWindows = TimeWindow::cases();

        return [
            [
                'name' => 'Budi Santoso', 'business' => 'Budi Teknik AC', 'phone' => '081111000001',
                'bio' => 'Spesialis AC split rumah dan kos di Malang utara. Datang tepat waktu, kerja rapi, dan selalu jelaskan masalahnya sebelum mulai.',
                'years' => 9, 'jobs' => 312, 'radius' => 10, 'response' => 4, 'promoted' => true, 'price_offset' => 0,
                'badge' => TechnicianBadge::Sertifikat, 'lat' => -7.9420, 'lng' => 112.6120,
                'areas' => ['Lowokwaru', 'Dinoyo', 'Tlogomas'], 'brands' => ['Daikin', 'Panasonic', 'Sharp', 'LG'],
                'services' => ['cuci', 'perbaikan', 'bongkar', 'berkala'], 'days' => $everyDay, 'windows' => $allWindows,
                'reviews' => [
                    ['Nadia Putri', 5, 'Datang sesuai jadwal, harga sama persis dengan estimasi. AC kos langsung dingin lagi.'],
                    ['Fajar Hidayat', 5, 'Dijelaskan dulu masalahnya apa, nggak asal ganti part. Recommended.'],
                    ['Sinta Maharani', 4, 'Kerjanya rapi, cuma datangnya agak mepet di akhir slot.'],
                ],
            ],
            [
                'name' => 'Rizky Pratama', 'business' => 'Rizky Cool Service', 'phone' => '081111000002',
                'bio' => 'Melayani cuci dan perbaikan AC untuk mahasiswa dan keluarga muda. Bisa dipanggil sore dan malam.',
                'years' => 5, 'jobs' => 156, 'radius' => 5, 'response' => 6, 'promoted' => false, 'price_offset' => -5000,
                'badge' => TechnicianBadge::Identitas, 'lat' => -7.9405, 'lng' => 112.6040,
                'areas' => ['Dinoyo', 'Lowokwaru', 'Sumbersari'], 'brands' => ['Sharp', 'Panasonic', 'Polytron', 'Aqua'],
                'services' => ['cuci', 'perbaikan', 'berkala'], 'days' => $everyDay, 'windows' => [TimeWindow::Siang, TimeWindow::Sore, TimeWindow::Malam],
                'reviews' => [
                    ['Bagus Setiawan', 5, 'Bisa datang malam habis saya pulang kerja. Mantap.'],
                    ['Laras Ayu', 5, 'Ramah dan cepat balas. Cuci AC 2 unit selesai sejam.'],
                ],
            ],
            [
                'name' => 'Slamet Riyadi', 'business' => 'Slamet Jaya AC', 'phone' => '081111000003',
                'bio' => 'Lima belas tahun menangani AC rumah, ruko, dan kantor. Paham kelistrikan dan instalasi pipa baru.',
                'years' => 15, 'jobs' => 540, 'radius' => 15, 'response' => 9, 'promoted' => false, 'price_offset' => 10000,
                'badge' => TechnicianBadge::Sertifikat, 'lat' => -7.9780, 'lng' => 112.6300,
                'areas' => ['Klojen', 'Sukun', 'Blimbing', 'Kedungkandang'], 'brands' => ['Daikin', 'Panasonic', 'LG', 'Samsung', 'Gree', 'Midea'],
                'services' => ['cuci', 'perbaikan', 'bongkar', 'berkala'], 'days' => [1, 2, 3, 4, 5, 6], 'windows' => [TimeWindow::Pagi, TimeWindow::Siang, TimeWindow::Sore],
                'reviews' => [
                    ['Hendri Wijaya', 5, 'Bongkar pasang AC pindahan rumah, rapi dan pipa dirapikan. Puas.'],
                    ['Maya Kartika', 4, 'Teknisinya senior, langsung tahu masalahnya. Harga sedikit di atas yang lain.'],
                    ['Rudi Hartono', 5, 'Sudah langganan servis berkala di kantor. Selalu konsisten.'],
                ],
            ],
            [
                'name' => 'Andi Wijaya', 'business' => 'Andi Sejuk Mandiri', 'phone' => '081111000004',
                'bio' => 'Teknisi muda yang fokus cuci AC dan perbaikan ringan dengan harga bersahabat.',
                'years' => 2, 'jobs' => 38, 'radius' => 5, 'response' => 12, 'promoted' => false, 'price_offset' => -10000,
                'badge' => TechnicianBadge::Terdaftar, 'lat' => -8.0050, 'lng' => 112.6190,
                'areas' => ['Sukun', 'Klojen'], 'brands' => ['Sharp', 'Polytron', 'Aqua', 'Midea'],
                'services' => ['cuci', 'perbaikan'], 'days' => [0, 2, 4, 6], 'windows' => [TimeWindow::Pagi, TimeWindow::Siang],
                'reviews' => [
                    ['Dewi Lestari', 4, 'Harga murah, hasil bersih. Perlu sedikit lebih cepat balas chat.'],
                ],
            ],
            [
                'name' => 'Yoga Saputra', 'business' => 'Yoga AC Service', 'phone' => '081111000005',
                'bio' => 'Spesialis bongkar-pasang dan perawatan berkala untuk rumah tangga di Malang timur.',
                'years' => 4, 'jobs' => 89, 'radius' => 10, 'response' => 7, 'promoted' => false, 'price_offset' => 0,
                'badge' => TechnicianBadge::Identitas, 'lat' => -7.9390, 'lng' => 112.6500,
                'areas' => ['Blimbing', 'Sawojajar', 'Singosari'], 'brands' => ['Gree', 'Midea', 'Samsung', 'LG'],
                'services' => ['cuci', 'bongkar', 'berkala'], 'days' => [1, 3, 5], 'windows' => [TimeWindow::Pagi, TimeWindow::Siang, TimeWindow::Sore],
                'reviews' => [
                    ['Agus Salim', 5, 'Pasang AC baru rapi, sudah dicek kebocoran juga.'],
                    ['Rina Oktavia', 4, 'Bagus, tapi jadwalnya cuma bisa hari tertentu.'],
                ],
            ],
            [
                'name' => 'Hendra Kusuma', 'business' => 'Hendra Teknik Pendingin', 'phone' => '081111000006',
                'bio' => 'Teknisi bersertifikat BNSP. Menangani semua merek, termasuk AC inverter dan kerusakan PCB.',
                'years' => 12, 'jobs' => 410, 'radius' => 10, 'response' => 5, 'promoted' => false, 'price_offset' => 5000,
                'badge' => TechnicianBadge::Sertifikat, 'lat' => -7.9760, 'lng' => 112.6610,
                'areas' => ['Sawojajar', 'Kedungkandang', 'Blimbing'], 'brands' => ['Daikin', 'Panasonic', 'Sharp', 'LG', 'Samsung', 'Gree'],
                'services' => ['cuci', 'perbaikan', 'bongkar', 'berkala'], 'days' => $everyDay, 'windows' => [TimeWindow::Pagi, TimeWindow::Siang, TimeWindow::Sore],
                'reviews' => [
                    ['Yusuf Maulana', 5, 'AC inverter error kode, langsung ketemu masalahnya di PCB. Biaya final sesuai yang disetujui.'],
                    ['Putri Anggraini', 5, 'Profesional, bawa alat lengkap, dan bersih setelah kerja.'],
                    ['Danang Prakoso', 5, 'Fast response, harga transparan.'],
                ],
            ],
        ];
    }
}
