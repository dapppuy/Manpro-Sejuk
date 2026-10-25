<?php

use App\Enums\DocumentType;
use App\Enums\TechnicianBadge;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake([
        'nominatim.openstreetmap.org/search*' => Http::response([['lat' => '-7.9420', 'lon' => '112.6120']]),
    ]);
    Storage::fake('local');

    $this->service = Service::factory()->create(['slug' => 'perbaikan']);
});

function filledRegistration(Service $service): Testable
{
    return Livewire::test('pages::mitra.register')
        ->set('name', 'Budi Santoso')
        ->set('business_name', 'Budi Sejuk Teknik')
        ->set('phone', '0812 3456 7890')
        ->set('email', 'budi@example.com')
        ->set('password', 'password')
        ->set('area', 'Lowokwaru, Malang')
        ->set('service_id', $service->id)
        ->set('radius', 10)
        ->set('ktp', UploadedFile::fake()->create('ktp.jpg', 120, 'image/jpeg'))
        ->set('agreed', true);
}

it('registers a technician with a certificate and waits for verification', function () {
    filledRegistration($this->service)
        ->set('certificate', UploadedFile::fake()->create('sertifikat.pdf', 200, 'application/pdf'))
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('mitra.verification'));

    $user = User::where('email', 'budi@example.com')->firstOrFail();
    $profile = $user->technicianProfile;

    expect($user->role)->toBe(UserRole::Technician)
        ->and($user->phone)->toBe('081234567890')
        ->and($profile->verification_status)->toBe(VerificationStatus::Pending)
        ->and($profile->badge)->toBe(TechnicianBadge::Terdaftar)
        ->and($profile->is_accepting_orders)->toBeFalse()
        ->and($profile->latitude)->toBe(-7.942)
        ->and($profile->services->pluck('id')->all())->toBe([$this->service->id])
        ->and($profile->documents->pluck('type')->all())->toEqualCanonicalizing([DocumentType::Ktp, DocumentType::Sertifikat]);

    $profile->documents->each(fn ($document) => Storage::disk('local')->assertExists($document->path));
    $this->assertAuthenticatedAs($user);
});

it('registers a technician with work experience instead of a certificate', function () {
    filledRegistration($this->service)
        ->set('competency', 'experience')
        ->set('experience_years', 5)
        ->set('reference_phone', '081398762210')
        ->set('experience_description', 'Servis AC rumah dan kos sejak 2020.')
        ->call('register')
        ->assertHasNoErrors();

    $profile = User::where('email', 'budi@example.com')->firstOrFail()->technicianProfile;

    expect($profile->experience->years)->toBe(5)
        ->and($profile->years_experience)->toBe(5)
        ->and($profile->documents->pluck('type')->all())->toBe([DocumentType::Ktp]);
});

it('requires a KTP photo and the chosen competency proof', function () {
    filledRegistration($this->service)
        ->set('ktp', null)
        ->call('register')
        ->assertHasErrors(['ktp' => 'required', 'certificate' => 'required']);

    expect(User::where('email', 'budi@example.com')->exists())->toBeFalse();
});

it('rejects a KTP that is not an image', function () {
    filledRegistration($this->service)
        ->set('ktp', UploadedFile::fake()->create('ktp.pdf', 100, 'application/pdf'))
        ->assertHasErrors(['ktp']);
});

it('requires the data statement to be accepted', function () {
    filledRegistration($this->service)
        ->set('certificate', UploadedFile::fake()->create('sertifikat.png', 120, 'image/png'))
        ->set('agreed', false)
        ->call('register')
        ->assertHasErrors(['agreed' => 'accepted']);
});
