<?php

namespace App\Actions\Technician;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\TechnicianBadge;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\TechnicianDocument;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Services\Geocoder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a technician account waiting for verification, with its identity and competency proof.
 */
class RegisterTechnician
{
    public function __construct(private Geocoder $geocoder) {}

    /**
     * @param  array{name: string, business_name: string, phone: string, email: string, password: string, area: string, service_id: int, radius: int}  $account
     * @param  array{years: int, reference_phone: string, description: string}|null  $experience
     */
    public function handle(array $account, UploadedFile $ktp, ?UploadedFile $certificate, ?array $experience): User
    {
        $coordinates = $this->geocoder->search($account['area']);

        return DB::transaction(function () use ($account, $ktp, $certificate, $experience, $coordinates) {
            $user = User::create([
                'name' => $account['name'],
                'email' => $account['email'],
                'phone' => $account['phone'],
                'password' => $account['password'],
                'role' => UserRole::Technician,
                'city' => config('sejuk.city'),
            ]);

            $technician = $user->technicianProfile()->create([
                'business_name' => $account['business_name'],
                'slug' => $this->uniqueSlug($account['business_name']),
                'years_experience' => $experience['years'] ?? 0,
                'service_radius_km' => $account['radius'],
                'base_area' => $account['area'],
                'latitude' => $coordinates['latitude'] ?? config('sejuk.default_location.latitude'),
                'longitude' => $coordinates['longitude'] ?? config('sejuk.default_location.longitude'),
                'badge' => TechnicianBadge::Terdaftar,
                'verification_status' => VerificationStatus::Pending,
                'is_accepting_orders' => false,
            ]);

            $technician->services()->attach($account['service_id']);
            $technician->serviceAreas()->create(['area_name' => Str::before($account['area'], ',')]);

            $this->storeDocument($technician, DocumentType::Ktp, $ktp);

            if ($certificate !== null) {
                $this->storeDocument($technician, DocumentType::Sertifikat, $certificate);
            }

            if ($experience !== null) {
                $technician->experience()->create($experience);
            }

            return $user;
        });
    }

    private function storeDocument(TechnicianProfile $technician, DocumentType $type, UploadedFile $file): void
    {
        $technician->documents()->create([
            'type' => $type,
            'path' => $file->store("technician-documents/{$technician->id}", TechnicianDocument::DISK),
            'status' => DocumentStatus::Pending,
        ]);
    }

    private function uniqueSlug(string $businessName): string
    {
        $base = Str::slug($businessName) ?: 'teknisi';
        $slug = $base;

        while (TechnicianProfile::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
