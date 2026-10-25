<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\TechnicianBadge;
use App\Enums\VerificationStatus;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');

    $this->admin = User::factory()->admin()->create();
    $this->technician = TechnicianProfile::factory()->pending()->create(['business_name' => 'Calon Mitra AC']);
    $this->ktp = $this->technician->documents()->create([
        'type' => DocumentType::Ktp,
        'path' => UploadedFile::fake()->create('ktp.jpg', 120, 'image/jpeg')->store('technician-documents', 'local'),
        'status' => DocumentStatus::Pending,
    ]);
});

it('lists pending technicians in the verification queue', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.verifications.index')
        ->assertSee('Calon Mitra AC');
});

it('approves a technician with the chosen badge and publishes the profile', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.verifications.show', ['technician' => $this->technician])
        ->set('badge', 'identitas')
        ->call('approve')
        ->assertRedirect(route('admin.verifications.index'));

    $this->technician->refresh();

    expect($this->technician->verification_status)->toBe(VerificationStatus::Approved)
        ->and($this->technician->badge)->toBe(TechnicianBadge::Identitas)
        ->and($this->technician->is_accepting_orders)->toBeTrue()
        ->and($this->technician->availabilities()->count())->toBeGreaterThan(0)
        ->and($this->ktp->refresh()->status)->toBe(DocumentStatus::Approved)
        ->and($this->ktp->reviewed_by)->toBe($this->admin->id);
});

it('rejects a technician only with a reason', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.verifications.show', ['technician' => $this->technician])
        ->call('reject')
        ->assertHasErrors(['reason' => 'required'])
        ->set('reason', 'Foto KTP buram, mohon kirim ulang.')
        ->call('reject')
        ->assertHasNoErrors();

    expect($this->technician->refresh())
        ->verification_status->toBe(VerificationStatus::Rejected)
        ->verification_note->toBe('Foto KTP buram, mohon kirim ulang.');
});

it('streams verification documents only to admins', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.documents.show', $this->ktp))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    $this->actingAs($this->technician->user)
        ->get(route('admin.documents.show', $this->ktp))
        ->assertForbidden();
});

it('lets admins change a badge and deactivate a technician', function () {
    $approved = TechnicianProfile::factory()->create();
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.technicians.index')
        ->call('changeBadge', $approved->id, 'sertifikat')
        ->call('toggleActive', $approved->id);

    expect($approved->refresh())
        ->badge->toBe(TechnicianBadge::Sertifikat)
        ->is_active->toBeFalse();
});

it('keeps technicians out of the admin verification pages', function () {
    $this->actingAs($this->technician->user)
        ->get(route('admin.verifications.show', $this->technician))
        ->assertForbidden();
});
