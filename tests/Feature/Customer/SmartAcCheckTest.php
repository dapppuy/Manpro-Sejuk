<?php

use App\Livewire\Customer\SmartAcCheck;
use App\Models\CustomerUnit;
use App\Models\Diagnosis;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    config(['services.gemini.api_key' => 'test-key', 'sejuk.smart_check_per_hour' => 2]);
    Storage::fake('local');
    Storage::fake('public');

    Http::preventStrayRequests();
    Http::fake([
        '*/models?key=*' => Http::response(['models' => []]),
        '*:generateContent*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode([
            'detected_unit_info' => ['brand' => 'Daikin', 'capacity_pk' => '1', 'refrigerant' => 'R32'],
            'possible_causes' => [['title' => 'Filter kotor', 'explanation' => 'Aliran udara tertahan.', 'likelihood' => 'tinggi']],
            'confidence_level' => 'tinggi',
            'confidence_score' => 80,
            'urgency_level' => 'sedang',
            'recommended_service_slug' => 'cuci-ac',
            'preliminary_advice' => 'Matikan AC sampai teknisi datang.',
        ])]]]]]]),
    ]);

    $this->customer = User::factory()->customer()->create();
});

it('is only open to customers', function (string $role) {
    $user = User::factory()->{$role}()->create();

    if ($role === 'technician') {
        TechnicianProfile::factory()->for($user)->create();
    }

    $this->actingAs($user)->get(route('customer.smart-check'))->assertRedirect($user->homeRoute());
})->with(['technician', 'admin']);

it('saves the diagnosis and keeps the photos private', function () {
    Livewire::actingAs($this->customer)->test(SmartAcCheck::class)
        ->set('complaintText', 'AC menyala tapi anginnya tidak dingin sama sekali.')
        ->set('conditionPhoto', UploadedFile::fake()->create('ac.jpg', 120, 'image/jpeg'))
        ->call('analyze')
        ->assertHasNoErrors()
        ->assertSet('errorMessage', null)
        ->assertSee('Filter kotor');

    $diagnosis = Diagnosis::sole();

    expect($diagnosis->user_id)->toBe($this->customer->id)
        ->and($diagnosis->recommended_service_slug)->toBe('cuci-ac');
    Storage::disk('local')->assertExists($diagnosis->condition_photo_path);
    Storage::disk('public')->assertMissing($diagnosis->condition_photo_path);
});

it('refuses an AC unit that belongs to someone else', function () {
    $otherUnit = CustomerUnit::create(['user_id' => User::factory()->customer()->create()->id, 'name' => 'AC kamar tetangga']);

    Livewire::actingAs($this->customer)->test(SmartAcCheck::class)
        ->set('customerUnitId', $otherUnit->id)
        ->set('complaintText', 'AC menyala tapi anginnya tidak dingin sama sekali.')
        ->call('analyze')
        ->assertHasErrors('customerUnitId');

    expect(Diagnosis::count())->toBe(0);
    Http::assertNothingSent();
});

it('limits how many analyses a customer can run per hour', function () {
    $page = Livewire::actingAs($this->customer)->test(SmartAcCheck::class)
        ->set('complaintText', 'AC menyala tapi anginnya tidak dingin sama sekali.');

    $page->call('analyze')->call('analyze')->call('analyze');

    expect(Diagnosis::count())->toBe(2);
    expect($page->get('errorMessage'))->toContain('2 analisis');
});
