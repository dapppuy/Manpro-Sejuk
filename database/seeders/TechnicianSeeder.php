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

/**
 * The six sample technicians from the Figma Make prototype (src/data.ts).
 */
class TechnicianSeeder extends Seeder
{
    /**
     * Reference point the sample distances are measured from: Jl. Kertoraharjo, Ketawanggede.
     */
    public const ORIGIN = [-7.9455, 112.6150];

    /**
     * Base reference prices per service: [service, note, min, max]. Each technician scales them.
     *
     * @var list<array{0: string, 1: string, 2: int, 3: int}>
     */
    private const BASE_PRICES = [
        ['cuci', '0,5–1 PK', 60000, 85000],
        ['cuci', '1,5–2 PK', 85000, 110000],
        ['perbaikan', 'Cek + perbaikan ringan', 100000, 250000],
        ['bongkar', 'Bongkar + pasang ulang', 300000, 450000],
        ['berkala', 'Per unit, per kunjungan', 75000, 95000],
    ];

    /**
     * @var list<string>
     */
    private const GALLERY = [
        'photo-1759772238012-9d5ad59ae637',
        'photo-1722131646940-b821a6cc6252',
        'photo-1762341123870-d706f257a12e',
        'photo-1758798157512-f0a864c696c9',
        'photo-1787675907927-ae62773f56f6',
        'photo-1698479603408-1a66a6d9e80f',
    ];

    /**
     * @var list<array{0: string, 1: string}>
     */
    private const REVIEWS = [
        ['Nadia Putri', 'Teknisi datang sesuai jadwal, komunikasinya jelas, dan AC kembali dingin.'],
        ['Raka Aditya', 'Pengerjaan rapi dan setiap tambahan biaya dijelaskan sebelum dikerjakan.'],
        ['Sinta Maharani', 'Respons cepat. Teknisi juga memberi saran perawatan supaya AC tidak cepat kotor.'],
        ['Fajar Nugroho', 'Masalah tetesan air berhasil diperbaiki dan area kerja dibersihkan kembali.'],
        ['Ayu Lestari', 'Hasil servis memuaskan. Suara AC jauh lebih halus setelah selesai dikerjakan.'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = Service::all()->keyBy('slug');

        foreach ($this->technicians() as $index => $data) {
            [$latitude, $longitude] = self::pointAt($data['distance'], $data['bearing']);

            $user = User::factory()->create([
                'name' => $data['name'],
                'email' => $data['id'].'@sejuk.test',
                'phone' => $data['phone'],
                'phone_verified_at' => now(),
                'role' => UserRole::Technician,
                'avatar_path' => self::unsplash($data['photo'], 600),
            ]);

            $technician = $user->technicianProfile()->create([
                'business_name' => $data['business'],
                'slug' => Str::slug($data['business']),
                'bio' => $data['bio'],
                'years_experience' => $data['years'],
                'jobs_count' => $data['jobs'],
                'service_radius_km' => 10,
                'base_area' => $data['areas'][0],
                'latitude' => $latitude,
                'longitude' => $longitude,
                'badge' => $data['badge'],
                'verification_status' => VerificationStatus::Approved,
                'verified_at' => now()->subMonths(6 - $index),
                'is_active' => true,
                'is_accepting_orders' => true,
                'is_promoted' => $data['promo'],
                'avg_response_minutes' => $data['response'],
                'rating_avg' => $data['rating'],
                'reviews_count' => $data['reviews'],
            ]);

            foreach ($data['services'] as $slug) {
                $technician->services()->attach($services[$slug]);
            }

            foreach (self::BASE_PRICES as [$slug, $note, $min, $max]) {
                if (in_array($slug, $data['services'], true)) {
                    $technician->prices()->create([
                        'service_id' => $services[$slug]->id,
                        'note' => $note,
                        'price_min' => self::roundPrice($min * $data['price_factor']),
                        'price_max' => self::roundPrice($max * $data['price_factor']),
                    ]);
                }
            }

            $technician->serviceAreas()->createMany(array_map(fn (string $area) => ['area_name' => $area], $data['areas']));
            $technician->brands()->createMany(array_map(fn (string $brand) => ['brand' => $brand], $data['brands']));
            $technician->photos()->createMany(array_map(fn (string $photo) => ['path' => self::unsplash($photo)], self::GALLERY));

            $this->seedAvailability($technician, $data['windows'], $data['available_today']);
            $this->seedReviews($technician, $index, $data['rating']);
        }
    }

    /**
     * @param  list<TimeWindow>  $windows
     */
    private function seedAvailability(TechnicianProfile $technician, array $windows, bool $availableToday): void
    {
        foreach (range(0, 6) as $day) {
            foreach ($windows as $window) {
                $technician->availabilities()->create(['day_of_week' => $day, 'time_window' => $window, 'is_available' => true]);
            }
        }

        if (! $availableToday) {
            foreach (TimeWindow::cases() as $window) {
                $technician->availabilities()->create(['date' => today(), 'time_window' => $window, 'is_available' => false]);
            }
        }
    }

    private function seedReviews(TechnicianProfile $technician, int $offset, float $rating): void
    {
        foreach (range(0, 4) as $index) {
            [$name, $comment] = self::REVIEWS[($index + $offset) % count(self::REVIEWS)];

            $customer = User::firstOrCreate(
                ['email' => Str::slug($name, '.').'@pelanggan.test'],
                ['name' => $name, 'password' => 'password', 'role' => UserRole::Customer, 'city' => 'Malang'],
            );

            $technician->reviews()->forceCreate([
                'customer_id' => $customer->id,
                'rating' => $index === 3 && $rating < 4.8 ? 4 : 5,
                'comment' => $comment,
                'created_at' => now()->subWeeks($index + 2),
                'updated_at' => now()->subWeeks($index + 2),
            ]);
        }
    }

    /**
     * Coordinates the given distance (km) and bearing (degrees) away from the origin.
     *
     * @return array{0: float, 1: float}
     */
    public static function pointAt(float $distanceKm, float $bearing): array
    {
        [$latitude, $longitude] = self::ORIGIN;
        $radians = deg2rad($bearing);

        return [
            round($latitude + ($distanceKm / 111) * cos($radians), 7),
            round($longitude + ($distanceKm / (111 * cos(deg2rad($latitude)))) * sin($radians), 7),
        ];
    }

    private static function roundPrice(float $amount): int
    {
        return (int) (round($amount / 5000) * 5000);
    }

    private static function unsplash(string $id, int $width = 800): string
    {
        return "https://images.unsplash.com/{$id}?auto=format&fit=crop&w={$width}&q=75";
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function technicians(): array
    {
        return [
            [
                'id' => 'budi', 'name' => 'Budi Santoso', 'business' => 'Budi Teknik AC', 'phone' => '081111000001',
                'photo' => 'photo-1782105208181-c5b3b8996ad8', 'badge' => TechnicianBadge::Sertifikat,
                'rating' => 4.9, 'reviews' => 128, 'distance' => 1.2, 'bearing' => 20, 'available_today' => true,
                'years' => 9, 'jobs' => 640, 'response' => 12, 'promo' => false, 'price_factor' => 1.0,
                'areas' => ['Ketawanggede', 'Dinoyo', 'Sumbersari', 'Lowokwaru'],
                'brands' => ['Daikin', 'Panasonic', 'Sharp', 'LG', 'Gree'],
                'services' => ['cuci', 'perbaikan', 'bongkar', 'berkala'],
                'windows' => [TimeWindow::Pagi, TimeWindow::Siang, TimeWindow::Sore],
                'bio' => 'Spesialis AC split rumah dan kos. Bersertifikat BNSP teknisi refrigerasi, terbiasa menangani AC inverter.',
            ],
            [
                'id' => 'rizky', 'name' => 'Rizky Maulana', 'business' => 'Rizky Cool Service', 'phone' => '081111000002',
                'photo' => 'photo-1634402153378-16bd852cd046', 'badge' => TechnicianBadge::Identitas,
                'rating' => 4.8, 'reviews' => 64, 'distance' => 0.8, 'bearing' => 140, 'available_today' => true,
                'years' => 4, 'jobs' => 210, 'response' => 8, 'promo' => false, 'price_factor' => 0.9,
                'areas' => ['Ketawanggede', 'Jatimulyo', 'Sumbersari'],
                'brands' => ['Sharp', 'Polytron', 'Aqua', 'Midea'],
                'services' => ['cuci', 'perbaikan', 'berkala'],
                'windows' => [TimeWindow::Siang, TimeWindow::Sore, TimeWindow::Malam],
                'bio' => 'Lulusan SMK teknik pendingin, buka jasa cuci dan servis AC untuk area sekitar kampus.',
            ],
            [
                'id' => 'slamet', 'name' => 'Slamet Riyadi', 'business' => 'CV Sejuk Mandiri', 'phone' => '081111000003',
                'photo' => 'photo-1661263989552-d82526d03b0f', 'badge' => TechnicianBadge::Sertifikat,
                'rating' => 4.7, 'reviews' => 203, 'distance' => 4.1, 'bearing' => 110, 'available_today' => true,
                'years' => 15, 'jobs' => 1320, 'response' => 20, 'promo' => true, 'price_factor' => 1.1,
                'areas' => ['Lowokwaru', 'Blimbing', 'Klojen', 'Dau'],
                'brands' => ['Daikin', 'Mitsubishi', 'Panasonic', 'LG', 'Samsung'],
                'services' => ['cuci', 'perbaikan', 'bongkar', 'berkala'],
                'windows' => [TimeWindow::Pagi, TimeWindow::Siang, TimeWindow::Sore],
                'bio' => 'Tim 4 teknisi untuk rumah, kos, dan kantor kecil. Melayani bongkar-pasang dengan pipa dan bracket sendiri.',
            ],
            [
                'id' => 'andi', 'name' => 'Andi Prasetyo', 'business' => 'Andi AC Panggilan', 'phone' => '081111000004',
                'photo' => 'photo-1731960745314-e12045ee74a5', 'badge' => TechnicianBadge::Terdaftar,
                'rating' => 4.6, 'reviews' => 21, 'distance' => 2.4, 'bearing' => 290, 'available_today' => false,
                'years' => 3, 'jobs' => 75, 'response' => 30, 'promo' => false, 'price_factor' => 0.85,
                'areas' => ['Dinoyo', 'Merjosari', 'Tlogomas'],
                'brands' => ['Sharp', 'Changhong', 'Aqua'],
                'services' => ['cuci', 'perbaikan'],
                'windows' => [TimeWindow::Pagi, TimeWindow::Siang],
                'bio' => 'Teknisi independen, belum bersertifikat. Fokus cuci AC dan perbaikan ringan dengan harga ramah kantong.',
            ],
            [
                'id' => 'yoga', 'name' => 'Yoga Pratama', 'business' => 'Yoga Teknik', 'phone' => '081111000005',
                'photo' => 'photo-1545696968-1a5245650b36', 'badge' => TechnicianBadge::Identitas,
                'rating' => 4.5, 'reviews' => 37, 'distance' => 3.6, 'bearing' => 320, 'available_today' => false,
                'years' => 6, 'jobs' => 180, 'response' => 25, 'promo' => false, 'price_factor' => 0.95,
                'areas' => ['Tlogomas', 'Landungsari', 'Dau'],
                'brands' => ['Panasonic', 'Sharp', 'Daikin'],
                'services' => ['cuci', 'perbaikan', 'bongkar'],
                'windows' => [TimeWindow::Siang, TimeWindow::Sore],
                'bio' => 'Berpengalaman di toko elektronik sebelum membuka jasa sendiri. Bisa servis kulkas juga.',
            ],
            [
                'id' => 'hendra', 'name' => 'Hendra Wijaya', 'business' => 'Hendra Klimatek', 'phone' => '081111000006',
                'photo' => 'photo-1516011362164-3095a82b0af9', 'badge' => TechnicianBadge::Terdaftar,
                'rating' => 4.9, 'reviews' => 12, 'distance' => 6.8, 'bearing' => 95, 'available_today' => true,
                'years' => 2, 'jobs' => 40, 'response' => 15, 'promo' => false, 'price_factor' => 0.8,
                'areas' => ['Blimbing', 'Kedungkandang'],
                'brands' => ['LG', 'Samsung', 'Gree'],
                'services' => ['cuci', 'berkala', 'perbaikan'],
                'windows' => [TimeWindow::Sore, TimeWindow::Malam],
                'bio' => 'Teknisi muda, baru bergabung. Datang tepat waktu dan rapi.',
            ],
        ];
    }
}
