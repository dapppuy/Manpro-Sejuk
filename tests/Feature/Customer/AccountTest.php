<?php

use App\Enums\OrderStatus;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(['nominatim.openstreetmap.org/search*' => Http::response([['lat' => '-7.9500', 'lon' => '112.6200']])]);

    $this->customer = User::factory()->customer()->create(['phone' => '081200000001', 'phone_verified_at' => now()]);
    $this->actingAs($this->customer);
});

it('shows the phone badge and order summary', function () {
    Order::factory()->for($this->customer, 'customer')->status(OrderStatus::Selesai)->create();
    Order::factory()->for($this->customer, 'customer')->status(OrderStatus::Terjadwal)->create();

    Livewire::test('pages::account')
        ->assertSee('Nomor HP terverifikasi')
        ->assertSet('name', $this->customer->name)
        ->assertSee('Ringkasan pesanan');
});

it('updates personal info and drops phone verification when the number changes', function () {
    Livewire::test('pages::account')
        ->call('edit')
        ->set('name', 'Dimas P.')
        ->set('phone', '+62 812-0000-0099')
        ->set('city', 'Batu')
        ->call('saveProfile')
        ->assertHasNoErrors();

    expect($this->customer->refresh())
        ->name->toBe('Dimas P.')
        ->phone->toBe('081200000099')
        ->city->toBe('Batu')
        ->phone_verified_at->toBeNull();
});

it('adds a geocoded address that becomes primary and the search location', function () {
    Livewire::test('pages::account')
        ->call('newAddress')
        ->set('label', 'Kos Melati')
        ->set('address', 'Jl. Kertoraharjo')
        ->set('detail', 'Kamar 12')
        ->call('saveAddress')
        ->assertHasNoErrors()
        ->assertSee('Kos Melati');

    $address = CustomerAddress::sole();

    expect($address->is_primary)->toBeTrue()
        ->and($address->latitude)->toBe(-7.95)
        ->and(session('sejuk.location.address'))->toBe('Jl. Kertoraharjo');
});

it('switches the primary address', function () {
    $first = CustomerAddress::factory()->for($this->customer)->primary()->create(['label' => 'Kos']);
    $second = CustomerAddress::factory()->for($this->customer)->create(['label' => 'Rumah']);

    Livewire::test('pages::account')->call('makePrimary', $second->id);

    expect($second->refresh()->is_primary)->toBeTrue()
        ->and($first->refresh()->is_primary)->toBeFalse()
        ->and(session('sejuk.location.label'))->toBe('Rumah');
});

it('promotes another address when the primary one is deleted', function () {
    $primary = CustomerAddress::factory()->for($this->customer)->primary()->create();
    $other = CustomerAddress::factory()->for($this->customer)->create();

    Livewire::test('pages::account')->call('deleteAddress', $primary->id);

    expect(CustomerAddress::find($primary->id))->toBeNull()
        ->and($other->refresh()->is_primary)->toBeTrue();
});

it('cannot change someone else address', function () {
    $foreign = CustomerAddress::factory()->create();

    Livewire::test('pages::account')->call('deleteAddress', $foreign->id)->assertNotFound();

    expect(CustomerAddress::find($foreign->id))->not->toBeNull();
});
