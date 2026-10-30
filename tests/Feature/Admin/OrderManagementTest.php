<?php

use App\Enums\FundStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\TechnicianProfile;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('lists active orders and finds one by code', function () {
    $active = Order::factory()->offered()->create();
    $done = Order::factory()->status(OrderStatus::Selesai)->create();

    Livewire::test('pages::admin.orders.index')
        ->assertSee('#'.$active->code)
        ->assertDontSee('#'.$done->code)
        ->set('status', 'semua')
        ->set('search', $done->code)
        ->assertSee('#'.$done->code)
        ->assertDontSee('#'.$active->code);
});

it('shows the status history of an order', function () {
    $order = Order::factory()->offered()->create();
    $order->statusLogs()->create(['to_status' => OrderStatus::MenungguRespons, 'note' => 'Permintaan dikirim']);

    $this->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('Riwayat status')
        ->assertSee('Permintaan dikirim');
});

it('cancels a paid order and marks the funds for refund', function () {
    $order = Order::factory()->withEstimate()->create(['status' => OrderStatus::Dikerjakan, 'final_total' => 300000, 'fund_status' => FundStatus::Ditahan]);

    Livewire::test('pages::admin.orders.show', ['order' => $order])
        ->call('cancel')
        ->assertHasErrors(['reason' => 'required'])
        ->set('reason', 'Teknisi tidak datang')
        ->call('cancel')
        ->assertHasNoErrors();

    expect($order->refresh())
        ->status->toBe(OrderStatus::Dibatalkan)
        ->fund_status->toBe(FundStatus::Dikembalikan)
        ->cancelled_reason->toBe('Dibatalkan admin: Teknisi tidak datang');

    $this->actingAs($order->customer)->get(route('orders.show', $order))->assertSee('Dana dikembalikan ke kamu');
});

it('resolves a dispute in favour of the technician', function () {
    $order = Order::factory()->withEstimate()->create(['status' => OrderStatus::Sengketa, 'final_total' => 400000, 'fund_status' => FundStatus::Ditahan, 'rework_count' => 3]);

    Livewire::test('pages::admin.orders.show', ['order' => $order])
        ->set('reason', 'Foto bukti menunjukkan AC berfungsi')
        ->call('resolve', true);

    expect($order->refresh())
        ->status->toBe(OrderStatus::Selesai)
        ->fund_status->toBe(FundStatus::Diteruskan)
        ->commission_amount->toBe(40000);
});

it('resolves a dispute with a refund to the customer', function () {
    $order = Order::factory()->withEstimate()->create(['status' => OrderStatus::Sengketa, 'final_total' => 400000, 'fund_status' => FundStatus::Ditahan]);

    Livewire::test('pages::admin.orders.show', ['order' => $order])
        ->set('reason', 'AC tetap rusak')
        ->call('resolve', false);

    expect($order->refresh())
        ->status->toBe(OrderStatus::Dibatalkan)
        ->fund_status->toBe(FundStatus::Dikembalikan);
});

it('keeps non-admins out of the admin order pages', function () {
    $order = Order::factory()->create();

    $this->actingAs(TechnicianProfile::factory()->create()->user)->get(route('admin.orders.show', $order))->assertForbidden();
    $this->actingAs($order->customer)->get(route('admin.orders.index'))->assertForbidden();
});
