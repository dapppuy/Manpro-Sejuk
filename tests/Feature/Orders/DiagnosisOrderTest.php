<?php

use App\Enums\OrderStatus;
use App\Models\Diagnosis;
use App\Models\Order;
use App\Models\Service;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->customer = User::factory()->customer()->create();
    $this->technician = TechnicianProfile::factory()->create();
    $this->diagnosis = Diagnosis::create([
        'user_id' => $this->customer->id,
        'complaint_text' => 'AC tidak dingin dan berbunyi keras.',
        'possible_causes' => [['title' => 'Kapasitor lemah', 'explanation' => 'Kompresor sulit menyala.', 'likelihood' => 'tinggi']],
        'confidence_level' => 'sedang',
        'confidence_score' => 65,
        'urgency_level' => 'tinggi',
        'recommended_service_slug' => 'perbaikan',
    ]);
});

it('sends the Cek AC Pintar result with the request', function () {
    Http::preventStrayRequests();
    Http::fake(['nominatim.openstreetmap.org/search*' => Http::response([['lat' => '-7.9455', 'lon' => '112.6150']])]);
    $this->travelTo(now()->setTime(9, 0));
    $repair = Service::factory()->create(['slug' => 'perbaikan']);
    $this->technician->services()->attach([Service::factory()->create()->id, $repair->id]);

    $this->actingAs($this->customer)->withSession(['sejuk.order_diagnosis' => $this->diagnosis->id]);

    Livewire::test('pages::requests.create', ['technician' => $this->technician])
        ->assertSet('diagnosis_id', $this->diagnosis->id)
        ->assertSet('service_id', $repair->id)
        ->assertSet('notes', 'AC tidak dingin dan berbunyi keras.')
        ->assertSee('Hasil Cek AC Pintar ikut dikirim ke teknisi')
        ->set('ac_capacity', '1')
        ->set('ac_brand', 'Sharp')
        ->set('address', 'Jl. Kertoraharjo')
        ->set('day', 1)
        ->set('time_window', 'pagi')
        ->call('submit')
        ->assertHasNoErrors();

    expect(Order::sole()->diagnosis_id)->toBe($this->diagnosis->id);
});

it('ignores a diagnosis that belongs to another customer', function () {
    $this->technician->services()->attach(Service::factory()->create());
    $stranger = User::factory()->customer()->create();

    $this->actingAs($stranger)->withSession(['sejuk.order_diagnosis' => $this->diagnosis->id]);

    Livewire::test('pages::requests.create', ['technician' => $this->technician])
        ->assertSet('diagnosis_id', null)
        ->set('diagnosis_id', $this->diagnosis->id)
        ->call('submit')
        ->assertHasErrors('diagnosis_id');
});

it('shows the AI estimate to the technician and asks for the actual cause', function () {
    Storage::fake('local');
    $order = Order::factory()->for($this->technician, 'technician')->for($this->customer, 'customer')->withEstimate()->create([
        'status' => OrderStatus::Dikerjakan,
        'diagnosis_id' => $this->diagnosis->id,
    ]);

    $this->actingAs($this->technician->user)->get(route('mitra.orders.show', $order))
        ->assertOk()
        ->assertSee(['Hasil Cek AC Pintar', 'Kapasitor lemah', 'Perkiraan']);

    $proof = Livewire::actingAs($this->technician->user)->test('pages::mitra.orders.proof', ['order' => $order])
        ->set('before', UploadedFile::fake()->create('sebelum.jpg', 100, 'image/jpeg'))
        ->set('after', UploadedFile::fake()->create('sesudah.jpg', 100, 'image/jpeg'));

    $proof->call('submit')->assertHasErrors(['actualCause', 'diagnosisAccurate']);

    $proof->set('actualCause', 'Kapasitor kipas outdoor lemah.')
        ->set('diagnosisAccurate', 'ya')
        ->call('submit')
        ->assertHasNoErrors();

    expect($this->diagnosis->refresh())
        ->actual_cause_by_technician->toBe('Kapasitor kipas outdoor lemah.')
        ->is_accurate->toBeTrue();
});
