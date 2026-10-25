<?php

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Service;
use App\Models\TechnicianProfile;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->service = Service::factory()->create(['slug' => 'cuci', 'name' => 'Cuci AC']);
    $this->customer = User::factory()->customer()->create();
    $this->technician = TechnicianProfile::factory()->alwaysAvailable()->create(['business_name' => 'Budi Teknik AC']);
    $this->technician->services()->attach($this->service);

    $this->actingAs($this->customer);
});

function customerOrder(array $states = []): Order
{
    $factory = Order::factory()->for(test()->customer, 'customer')->for(test()->service)->for(test()->technician, 'technician');

    foreach ($states as $state => $argument) {
        $factory = is_int($state) ? $factory->{$argument}() : $factory->{$state}($argument);
    }

    return $factory->create();
}

function nearbyTechnician(Service $service, float $offsetKm, array $attributes = []): TechnicianProfile
{
    $technician = TechnicianProfile::factory()->at(-7.9435 + $offsetKm / 111, 112.6150)->alwaysAvailable()->create($attributes);
    $technician->services()->attach($service);

    return $technician;
}

it('shows the countdown while waiting for the technician', function () {
    $order = customerOrder(['offered']);

    $this->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('Menunggu respons '.str($this->technician->user->name)->before(' '))
        ->assertSee('Cari teknisi cadangan sekarang');
});

it('forbids other customers from opening the order', function () {
    $order = customerOrder(['offered']);

    $this->actingAs(User::factory()->customer()->create())
        ->get(route('orders.show', $order))
        ->assertForbidden();
});

it('lets the customer stop waiting and pick from up to three backup technicians', function () {
    $order = customerOrder(['offered']);
    $near = nearbyTechnician($this->service, 0.5, ['business_name' => 'Cadangan Dekat']);
    nearbyTechnician($this->service, 1.0, ['business_name' => 'Cadangan Kedua']);
    nearbyTechnician($this->service, 1.5, ['business_name' => 'Cadangan Ketiga']);
    nearbyTechnician($this->service, 2.0, ['business_name' => 'Cadangan Keempat']);
    nearbyTechnician($this->service, 0.2, ['business_name' => 'Sedang Libur', 'is_accepting_orders' => false]);

    $page = Livewire::test('pages::orders.show', ['order' => $order])
        ->call('requestBackup')
        ->assertSeeInOrder(['Cadangan Dekat', 'Cadangan Kedua', 'Cadangan Ketiga'])
        ->assertDontSee('Cadangan Keempat')
        ->assertDontSee('Sedang Libur');

    expect($order->refresh()->status)->toBe(OrderStatus::CariCadangan)
        ->and($order->offers()->sole()->status)->toBe(OfferStatus::Kedaluwarsa);

    $page->call('chooseBackup', $near->id);

    expect($order->refresh())
        ->status->toBe(OrderStatus::MenungguRespons)
        ->technician_id->toBe($near->id)
        ->and($order->offers()->where('technician_id', $near->id)->value('status'))->toBe(OfferStatus::Menunggu);
});

it('refuses a backup technician who is not among the suggestions', function () {
    $order = customerOrder(['status' => OrderStatus::CariCadangan, 'offered' => OfferStatus::Ditolak]);
    $far = nearbyTechnician($this->service, 40);

    Livewire::test('pages::orders.show', ['order' => $order])->call('chooseBackup', $far->id);

    expect($order->refresh()->technician_id)->toBe($this->technician->id);
});

it('moves an unanswered request to backup selection after 15 minutes', function () {
    $order = customerOrder(['offered']);

    $this->travel(16)->minutes();
    $this->artisan('orders:expire-responses')->assertSuccessful();

    expect($order->refresh()->status)->toBe(OrderStatus::CariCadangan)
        ->and($order->offers()->sole()->status)->toBe(OfferStatus::Kedaluwarsa)
        ->and($order->statusLogs()->latest('id')->value('note'))->toContain('tidak merespons');
});

it('expires an overdue request when the customer opens the page', function () {
    $order = customerOrder(['offered']);
    $this->travel(16)->minutes();

    $this->get(route('orders.show', $order))->assertSee('Teknisi cadangan siap membantu');

    expect($order->refresh()->status)->toBe(OrderStatus::CariCadangan);
});

it('schedules the visit when the estimate is approved', function () {
    $order = customerOrder(['withEstimate']);

    Livewire::test('pages::orders.show', ['order' => $order])
        ->assertSee('Rp100.000')
        ->call('approveEstimate')
        ->assertSee('Pesanan terjadwal');

    expect($order->refresh()->status)->toBe(OrderStatus::Terjadwal);
});

it('returns to search with the technician hidden after rejecting the estimate', function () {
    $order = customerOrder(['withEstimate']);

    Livewire::test('pages::orders.show', ['order' => $order])
        ->call('rejectEstimate')
        ->assertRedirect(route('search', ['layanan' => 'cuci']));

    expect($order->refresh())
        ->status->toBe(OrderStatus::CariCadangan)
        ->estimate_min->toBeNull()
        ->and(session('sejuk.hidden_technicians'))->toBe([$this->technician->id])
        ->and(session('sejuk.pending_order'))->toBe($order->code)
        ->and(session('sejuk.rejected_notice'))->toContain('Budi Teknik AC');
});

it('cancels for free before the technician sets off', function (OrderStatus $status) {
    $order = customerOrder(['status' => $status, 'offered']);

    Livewire::test('pages::orders.show', ['order' => $order])
        ->call('cancel')
        ->assertSee('Biaya pembatalan');

    expect($order->refresh()->status)->toBe(OrderStatus::Dibatalkan)
        ->and($order->offers()->where('status', OfferStatus::Menunggu)->exists())->toBeFalse();
})->with([OrderStatus::MenungguRespons, OrderStatus::CariCadangan, OrderStatus::EstimasiMasuk, OrderStatus::Terjadwal]);

it('does not cancel once payment is pending', function () {
    $order = customerOrder(['status' => OrderStatus::MenungguPembayaran]);

    Livewire::test('pages::orders.show', ['order' => $order])
        ->assertDontSee('Batalkan pesanan')
        ->call('cancel');

    expect($order->refresh()->status)->toBe(OrderStatus::MenungguPembayaran);
});

it('lists active orders and history on the orders page', function () {
    customerOrder(['offered']);
    customerOrder(['status' => OrderStatus::Dibatalkan]);

    $this->get(route('orders.index'))
        ->assertOk()
        ->assertSee('Sedang berjalan')
        ->assertSee('Menunggu respons')
        ->assertSee('Dibatalkan');
});
