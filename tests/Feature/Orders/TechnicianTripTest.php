<?php

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Models\TechnicianProfile;
use App\Notifications\OrderUpdated;
use App\Services\OrderWorkflow;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(now()->setTime(9, 0));
    $this->technician = TechnicianProfile::factory()->create(['business_name' => 'Budi Teknik AC']);
    $this->order = Order::factory()->for($this->technician, 'technician')->withEstimate()->status(OrderStatus::Terjadwal)->create();
});

it('tells the customer the technician is on the way with an arrival estimate', function () {
    Notification::fake();

    Livewire::actingAs($this->technician->user)->test('pages::mitra.orders.show', ['order' => $this->order])
        ->set('etaMinutes', 45)
        ->call('startTrip')
        ->assertHasNoErrors();

    expect($this->order->refresh())
        ->status->toBe(OrderStatus::DalamPerjalanan)
        ->trip_eta_minutes->toBe(45);
    Notification::assertSentTo($this->order->customer, OrderUpdated::class, fn (OrderUpdated $notification) => $notification->title === 'Teknisi sedang menuju lokasimu'
        && str_contains($notification->body, 'sekitar pukul 09.45'));

    $this->actingAs($this->order->customer)->get(route('orders.show', $this->order))
        ->assertOk()
        ->assertSee(['Teknisi sedang menuju lokasimu', '09.45']);
});

it('tells the customer when the technician arrives and then lets the final cost be sent', function () {
    Notification::fake();
    $this->order->update(['status' => OrderStatus::DalamPerjalanan, 'trip_started_at' => now()]);

    Livewire::actingAs($this->technician->user)->test('pages::mitra.orders.show', ['order' => $this->order])
        ->call('markArrived');

    expect($this->order->refresh()->status)->toBe(OrderStatus::Pemeriksaan)
        ->and($this->order->arrived_at)->not->toBeNull();
    Notification::assertSentTo($this->order->customer, OrderUpdated::class, fn (OrderUpdated $notification) => $notification->title === 'Teknisi sudah tiba');

    $this->actingAs($this->technician->user)->get(route('mitra.orders.final-cost', $this->order))->assertOk();
});

it('rejects the trip steps from another technician and out of order', function () {
    $stranger = TechnicianProfile::factory()->create();

    Livewire::actingAs($stranger->user)->test('pages::mitra.orders.show', ['order' => $this->order])->assertForbidden();
    expect(fn () => app(OrderWorkflow::class)->startTrip($this->order, $stranger, 30))->toThrow(InvalidOrderTransition::class);

    $this->order->update(['status' => OrderStatus::MenungguPembayaran]);
    Livewire::actingAs($this->technician->user)->test('pages::mitra.orders.show', ['order' => $this->order])->call('startTrip');

    expect($this->order->refresh()->status)->toBe(OrderStatus::MenungguPembayaran);
});

it('keeps the order from being cancelled by the customer once the technician has left', function () {
    $this->order->update(['status' => OrderStatus::DalamPerjalanan]);

    expect($this->order->status->isCancellableByCustomer())->toBeFalse();
});
