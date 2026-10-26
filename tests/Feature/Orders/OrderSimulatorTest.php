<?php

use App\Enums\FundStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Service;
use App\Models\TechnicianProfile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');

    $service = Service::factory()->create(['name' => 'Cuci AC']);
    $technician = TechnicianProfile::factory()->create();
    $technician->services()->attach($service);
    $technician->prices()->create(['service_id' => $service->id, 'note' => '0,5–1 PK', 'price_min' => 60000, 'price_max' => 85000]);

    $this->order = Order::factory()->for($service)->for($technician, 'technician')->offered()->create(['ac_capacity' => '1']);
    $this->actingAs($this->order->customer);
});

it('walks an order from request to completion in local mode', function () {
    app()['env'] = 'local';

    $page = Livewire::test('pages::orders.show', ['order' => $this->order])->assertSee('Panel simulasi');

    $page->call('simulate', 'sendEstimate');
    expect($this->order->refresh()->status)->toBe(OrderStatus::EstimasiMasuk)
        ->and($this->order->estimate_min)->toBe(60000);

    $page->call('approveEstimate');
    $page->call('simulate', 'sendFinalCostWithExtras');
    expect($this->order->refresh()->status)->toBe(OrderStatus::MenungguPersetujuanBiaya)
        ->and($this->order->costItems()->where('is_additional', true)->count())->toBe(2);

    $page->call('approveFinalCost');
    $page->call('simulate', 'failPayment')->assertSee('Pembayaran gagal');
    expect($this->order->refresh()->status)->toBe(OrderStatus::MenungguPembayaran);

    $page->call('simulate', 'payNow');
    expect($this->order->refresh()->status)->toBe(OrderStatus::Dikerjakan)
        ->and($this->order->fund_status)->toBe(FundStatus::Ditahan);

    $page->call('simulate', 'uploadProof')->assertSee('Cek dulu sebelum menandai selesai');
    expect($this->order->refresh()->workProofs()->count())->toBe(1);

    $page->call('simulate', 'autoComplete')->assertSee('AC kamu sudah beres');
    expect($this->order->refresh())
        ->status->toBe(OrderStatus::Selesai)
        ->fund_status->toBe(FundStatus::Diteruskan);
});

it('lets the technician decline or time out in local mode', function () {
    app()['env'] = 'local';

    Livewire::test('pages::orders.show', ['order' => $this->order])
        ->call('simulate', 'expireResponse')
        ->assertSee('Teknisi cadangan siap membantu');

    expect($this->order->refresh()->status)->toBe(OrderStatus::CariCadangan);
});

it('ignores steps that do not fit the current status', function () {
    app()['env'] = 'local';

    Livewire::test('pages::orders.show', ['order' => $this->order])->call('simulate', 'payNow');

    expect($this->order->refresh()->status)->toBe(OrderStatus::MenungguRespons);
});

it('hides the panel and refuses simulation outside local', function () {
    Livewire::test('pages::orders.show', ['order' => $this->order])
        ->assertDontSee('Panel simulasi')
        ->call('simulate', 'sendEstimate')
        ->assertNotFound();

    expect($this->order->refresh()->status)->toBe(OrderStatus::MenungguRespons);
});
