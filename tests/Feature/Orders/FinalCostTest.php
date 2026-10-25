<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Service;
use App\Models\TechnicianProfile;
use App\Services\OrderWorkflow;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->service = Service::factory()->create(['slug' => 'perbaikan', 'name' => 'Perbaikan']);
    $this->technician = TechnicianProfile::factory()->create();
    $this->order = Order::factory()
        ->for($this->service)
        ->for($this->technician, 'technician')
        ->withEstimate(250000, 450000)
        ->create(['status' => OrderStatus::Terjadwal, 'warranty' => true, 'warranty_fee' => 15000, 'ac_capacity' => '1']);
});

function sendFinalCost(Order $order, array $items): Testable
{
    return Livewire::test('pages::mitra.orders.final-cost', ['order' => $order])
        ->set('items', $items)
        ->call('submit');
}

it('prefills the first line from the service and the estimate midpoint', function () {
    $this->actingAs($this->technician->user);

    Livewire::test('pages::mitra.orders.final-cost', ['order' => $this->order])
        ->assertSet('items.0.label', 'Perbaikan · 1 PK')
        ->assertSet('items.0.amount', 350000)
        ->assertSet('items.0.is_additional', false);
});

it('sends the final cost with additional work and the chosen warranty', function () {
    $this->actingAs($this->technician->user);

    sendFinalCost($this->order, [
        ['label' => 'Jasa pemeriksaan & perbaikan', 'amount' => 200000, 'is_additional' => false],
        ['label' => 'Isi ulang freon R32', 'amount' => 150000, 'is_additional' => true],
    ])->assertHasNoErrors()->assertRedirect(route('mitra.orders.show', $this->order));

    $this->order->refresh();

    expect($this->order->status)->toBe(OrderStatus::MenungguPersetujuanBiaya)
        ->and($this->order->final_total)->toBe(365000)
        ->and($this->order->costItems->pluck('label')->all())->toBe(['Jasa pemeriksaan & perbaikan', 'Isi ulang freon R32', 'Garansi Servis 30 Hari'])
        ->and($this->order->costItems->where('is_additional', true)->count())->toBe(1)
        ->and($this->order->statusLogs()->latest('id')->value('to_status'))->toBe(OrderStatus::MenungguPersetujuanBiaya);
});

it('validates every cost line', function () {
    $this->actingAs($this->technician->user);

    sendFinalCost($this->order, [['label' => '', 'amount' => -5, 'is_additional' => false]])
        ->assertHasErrors(['items.0.label' => 'required', 'items.0.amount' => 'min']);

    expect($this->order->refresh()->status)->toBe(OrderStatus::Terjadwal);
});

it('lets the technician revise the final cost before the customer decides', function () {
    $this->actingAs($this->technician->user);
    app(OrderWorkflow::class)->submitFinalCost($this->order, $this->technician, [['label' => 'Jasa', 'amount' => 300000, 'is_additional' => false]]);

    sendFinalCost($this->order->refresh(), [['label' => 'Jasa', 'amount' => 280000, 'is_additional' => false]])->assertHasNoErrors();

    expect($this->order->refresh())
        ->status->toBe(OrderStatus::MenungguPersetujuanBiaya)
        ->final_total->toBe(295000)
        ->and($this->order->costItems()->count())->toBe(2);
});

it('sends the technician back to the order before the visit is scheduled', function () {
    $this->actingAs($this->technician->user);
    $this->order->update(['status' => OrderStatus::EstimasiMasuk]);

    $this->get(route('mitra.orders.final-cost', $this->order))->assertRedirect(route('mitra.orders.show', $this->order));
});

it('keeps other technicians away from the final cost form', function () {
    $this->actingAs(TechnicianProfile::factory()->create()->user)
        ->get(route('mitra.orders.final-cost', $this->order))
        ->assertForbidden();
});

it('shows new items and the amount above the estimate to the customer', function () {
    app(OrderWorkflow::class)->submitFinalCost($this->order, $this->technician, [
        ['label' => 'Jasa perbaikan', 'amount' => 400000, 'is_additional' => false],
        ['label' => 'Ganti kapasitor 35 µF', 'amount' => 85000, 'is_additional' => true],
    ]);

    $this->actingAs($this->order->customer)
        ->get(route('orders.show', $this->order))
        ->assertSee('Biaya berubah setelah pemeriksaan')
        ->assertSee('Ganti kapasitor 35 µF')
        ->assertSee('Baru')
        ->assertSee('+Rp35.000 di atas estimasi');
});

it('moves to payment when the customer approves the final cost', function () {
    app(OrderWorkflow::class)->submitFinalCost($this->order, $this->technician, [['label' => 'Jasa', 'amount' => 300000, 'is_additional' => false]]);
    $this->actingAs($this->order->customer);

    Livewire::test('pages::orders.show', ['order' => $this->order->refresh()])
        ->call('approveFinalCost')
        ->assertSee('Biaya final disetujui');

    expect($this->order->refresh()->status)->toBe(OrderStatus::MenungguPembayaran);
});

it('closes the order without work when the customer rejects the final cost', function () {
    app(OrderWorkflow::class)->submitFinalCost($this->order, $this->technician, [['label' => 'Jasa', 'amount' => 600000, 'is_additional' => false]]);
    $this->actingAs($this->order->customer);

    Livewire::test('pages::orders.show', ['order' => $this->order->refresh()])
        ->call('$set', 'confirmRejectFinal', true)
        ->assertSee('Tolak biaya final?')
        ->call('rejectFinalCost')
        ->assertSee('Pekerjaan tidak dimulai')
        ->assertSee('Biaya kunjungan');

    expect($this->order->refresh())
        ->status->toBe(OrderStatus::Dibatalkan)
        ->cancelled_reason->toBe(OrderWorkflow::REASON_FINAL_COST_REJECTED);
});

it('shows the technician that the customer rejected the final cost', function () {
    app(OrderWorkflow::class)->submitFinalCost($this->order, $this->technician, [['label' => 'Jasa', 'amount' => 600000, 'is_additional' => false]]);
    app(OrderWorkflow::class)->rejectFinalCost($this->order->refresh(), $this->order->customer);

    $this->actingAs($this->technician->user)
        ->get(route('mitra.orders.show', $this->order))
        ->assertSee('Pelanggan menolak biaya final');
});
