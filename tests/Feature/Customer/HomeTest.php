<?php

use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('asks for an address before searching a service', function () {
    $service = Service::factory()->create(['slug' => 'cuci']);

    Livewire::test('pages::home')
        ->call('searchService', $service->slug)
        ->assertSet('showAddressWarning', true)
        ->assertSee('Isi alamat dulu')
        ->assertNoRedirect();
});

it('geocodes a typed address and opens the search for that service', function () {
    Http::fake([
        'nominatim.openstreetmap.org/search*' => Http::response([['lat' => '-7.9435', 'lon' => '112.6150']]),
    ]);

    Livewire::test('pages::home')
        ->set('address', 'Jl. MT Haryono 12')
        ->call('searchService', 'cuci')
        ->assertRedirect(route('search', ['layanan' => 'cuci']));

    expect(session('sejuk.location'))->toMatchArray([
        'address' => 'Jl. MT Haryono 12',
        'latitude' => -7.9435,
        'longitude' => 112.6150,
    ]);

    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'Jl. MT Haryono 12, Malang'));
});

it('fills the address from the browser location', function () {
    Http::fake([
        'nominatim.openstreetmap.org/reverse*' => Http::response([
            'address' => ['road' => 'Jalan Soekarno Hatta', 'suburb' => 'Lowokwaru', 'city' => 'Malang'],
        ]),
    ]);

    Livewire::test('pages::home')
        ->call('useCoordinates', -7.94, 112.62)
        ->assertSet('address', 'Jalan Soekarno Hatta, Lowokwaru, Malang')
        ->assertSet('latitude', -7.94);
});

it('asks to type the address when location permission is denied', function () {
    Livewire::test('pages::home')
        ->call('locationDenied')
        ->assertSee('Izin lokasi tidak aktif');
});
