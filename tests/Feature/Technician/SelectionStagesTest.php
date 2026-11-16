<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\VerificationStatus;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Notifications\TechnicianSelectionUpdated;
use App\Support\TechnicianSelection;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->technician = TechnicianProfile::factory()->pending()->create();
    $this->technician->user->update(['phone' => '081234567890']);
});

it('keeps approval locked until every stage is passed and the agreement is accepted', function () {
    Livewire::actingAs($this->admin)->test('pages::admin.verifications.show', ['technician' => $this->technician])
        ->assertSee(['Tahapan seleksi', 'Wawancara & tes pengetahuan', 'https://wa.me/6281234567890'])
        ->set('badge', 'identitas')
        ->call('approve')
        ->assertHasErrors('badge');

    expect($this->technician->refresh()->verification_status)->toBe(VerificationStatus::Pending);
});

it('records a passed stage and tells the applicant what comes next', function () {
    Notification::fake();

    Livewire::actingAs($this->admin)->test('pages::admin.verifications.show', ['technician' => $this->technician])
        ->set('stageNotes.wawancara', 'Nilai 5/5')
        ->call('passStage', 'wawancara')
        ->assertHasNoErrors();

    $result = TechnicianSelection::for($this->technician->refresh())->result('wawancara');

    expect($result['note'])->toBe('Nilai 5/5')
        ->and($result['by'])->toBe($this->admin->id);
    Notification::assertSentTo($this->technician->user, TechnicianSelectionUpdated::class);
});

it('lets the admin skip the interview, practical test, and reference check only for certified applicants', function () {
    Notification::fake();
    $page = Livewire::actingAs($this->admin)->test('pages::admin.verifications.show', ['technician' => $this->technician]);

    $page->call('skipStage', 'uji_praktik')->assertStatus(422);

    $this->technician->documents()->create(['type' => DocumentType::Sertifikat, 'path' => 'x.pdf', 'status' => DocumentStatus::Pending]);
    $page = Livewire::actingAs($this->admin)->test('pages::admin.verifications.show', ['technician' => $this->technician->load('documents')]);

    foreach (['wawancara', 'uji_praktik', 'referensi'] as $stage) {
        $page->call('skipStage', $stage)->assertHasNoErrors();
    }
    $page->call('skipStage', 'orientasi')->assertStatus(422);

    $selection = TechnicianSelection::for($this->technician->refresh()->load('documents'));

    expect($selection->isSkipped('uji_praktik'))->toBeTrue()
        ->and($selection->currentStage())->toBe('dokumen');
    Notification::assertSentToTimes($this->technician->user, TechnicianSelectionUpdated::class, 3);
});

it('still lets the admin run the optional stages for certified applicants', function () {
    $this->technician->documents()->create(['type' => DocumentType::Sertifikat, 'path' => 'x.pdf', 'status' => DocumentStatus::Pending]);

    Livewire::actingAs($this->admin)->test('pages::admin.verifications.show', ['technician' => $this->technician->load('documents')])
        ->assertSee('Opsional · bersertifikat')
        ->call('passStage', 'wawancara');

    expect(TechnicianSelection::for($this->technician->refresh())->isSkipped('wawancara'))->toBeFalse()
        ->and(TechnicianSelection::for($this->technician)->isPassed('wawancara'))->toBeTrue();
});

it('lets the applicant follow the stages and accept the partnership agreement', function () {
    Livewire::actingAs($this->technician->user)->test('pages::mitra.verification')
        ->assertSee(['Tahapan seleksi', 'Pemeriksaan dokumen', 'Chat tim seleksi'])
        ->call('acceptAgreement')
        ->assertHasErrors('agreesToPartnership')
        ->set('agreesToPartnership', true)
        ->call('acceptAgreement')
        ->assertHasNoErrors();

    expect($this->technician->refresh()->agreement_accepted_at)->not->toBeNull();
});
