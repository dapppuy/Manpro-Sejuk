<?php

use App\Enums\MediaType;
use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Enums\TimeWindow;
use App\Models\Order;
use App\Models\Service;
use App\Models\TechnicianProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->service = Service::factory()->create(['slug' => 'cuci', 'name' => 'Cuci AC']);
    $this->technician = TechnicianProfile::factory()->create(['avg_response_minutes' => null]);
    $this->technician->services()->attach($this->service);
    $this->technician->prices()->createMany([
        ['service_id' => $this->service->id, 'note' => '0,5–1 PK', 'price_min' => 60000, 'price_max' => 85000],
        ['service_id' => $this->service->id, 'note' => '1,5–2 PK', 'price_min' => 85000, 'price_max' => 110000],
    ]);

    $this->order = Order::factory()->for($this->service)->for($this->technician, 'technician')->offered()->create(['ac_capacity' => '1.5', 'time_window' => TimeWindow::Siang]);

    $this->actingAs($this->technician->user);
});

it('shows the incoming order with its response countdown', function () {
    $this->get(route('mitra.orders.show', $this->order))
        ->assertOk()
        ->assertSee('Detail Pesanan Masuk')
        ->assertSee('Kirim Penawaran');
});

it('lets the technician decline so the customer can pick a backup', function () {
    Livewire::test('pages::mitra.orders.show', ['order' => $this->order])
        ->call('reject')
        ->assertRedirect(route('mitra.orders.index'));

    expect($this->order->refresh()->status)->toBe(OrderStatus::CariCadangan)
        ->and($this->order->offers()->sole()->status)->toBe(OfferStatus::Ditolak);
});

it('prefills the estimate from reference prices for larger units', function () {
    Livewire::test('pages::mitra.orders.estimate', ['order' => $this->order])
        ->assertSet('price_min', 85000)
        ->assertSet('price_max', 110000)
        ->assertSet('time_window', 'siang');
});

it('sends an estimate and schedule to the customer', function () {
    $this->travel(4)->minutes();

    Livewire::test('pages::mitra.orders.estimate', ['order' => $this->order])
        ->set('price_min', 90000)
        ->set('price_max', 120000)
        ->set('visit_date', now()->addDays(2)->toDateString())
        ->set('time_window', 'sore')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('mitra.orders.show', $this->order));

    expect($this->order->refresh())
        ->status->toBe(OrderStatus::EstimasiMasuk)
        ->estimate_min->toBe(90000)
        ->estimate_max->toBe(120000)
        ->scheduled_window->toBe(TimeWindow::Sore)
        ->response_deadline->toBeNull()
        ->and($this->order->offers()->sole()->status)->toBe(OfferStatus::Diterima)
        ->and($this->technician->refresh()->avg_response_minutes)->toBe(4);
});

it('rejects an estimate maximum below the minimum', function () {
    Livewire::test('pages::mitra.orders.estimate', ['order' => $this->order])
        ->set('price_min', 120000)
        ->set('price_max', 90000)
        ->call('submit')
        ->assertHasErrors(['price_max' => 'gte']);

    expect($this->order->refresh()->status)->toBe(OrderStatus::MenungguRespons);
});

it('sends the technician back to the order once the response window expired', function () {
    $this->travel(16)->minutes();
    $this->artisan('orders:expire-responses');

    $this->get(route('mitra.orders.estimate', $this->order))->assertRedirect(route('mitra.orders.show', $this->order));
});

it('keeps other technicians out of the order', function () {
    $stranger = TechnicianProfile::factory()->create();

    $this->actingAs($stranger->user)->get(route('mitra.orders.show', $this->order))->assertForbidden();
    $this->actingAs($stranger->user)->get(route('mitra.orders.estimate', $this->order))->assertForbidden();
});

it('streams damage photos only to people on the order', function () {
    Storage::fake('local');
    $media = $this->order->media()->create([
        'path' => UploadedFile::fake()->create('unit.jpg', 100, 'image/jpeg')->store('order-media', 'local'),
        'type' => MediaType::Foto,
    ]);

    $this->get(route('orders.media', $media))->assertOk();
    $this->actingAs($this->order->customer)->get(route('orders.media', $media))->assertOk();
    $this->actingAs(TechnicianProfile::factory()->create()->user)->get(route('orders.media', $media))->assertForbidden();
});

it('lists incoming orders in the inbox', function () {
    Livewire::test('pages::mitra.orders.index')
        ->assertSee('#'.$this->order->code)
        ->set('tab', 'riwayat')
        ->assertDontSee('#'.$this->order->code);
});
