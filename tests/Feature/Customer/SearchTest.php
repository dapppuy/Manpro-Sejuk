<?php

use App\Enums\TechnicianBadge;
use App\Models\Service;
use App\Models\TechnicianProfile;
use Livewire\Livewire;

/**
 * Customer location used by every test: Jl. MT Haryono, Lowokwaru.
 */
beforeEach(function () {
    session(['sejuk.location' => [
        'address' => 'Jl. MT Haryono',
        'label' => null,
        'detail' => null,
        'latitude' => -7.9435,
        'longitude' => 112.6150,
    ]]);

    $this->service = Service::factory()->create(['slug' => 'cuci', 'name' => 'Cuci AC']);
});

function technicianOffering(Service $service, float $latitude, float $longitude, array $attributes = []): TechnicianProfile
{
    $technician = TechnicianProfile::factory()->at($latitude, $longitude)->alwaysAvailable()->create($attributes);
    $technician->services()->attach($service);
    $technician->prices()->create(['service_id' => $service->id, 'note' => '0,5–1 PK', 'price_min' => 65000, 'price_max' => 80000]);

    return $technician;
}

it('lists only technicians within the chosen radius', function () {
    $near = technicianOffering($this->service, -7.9440, 112.6160, ['business_name' => 'Dekat AC']);
    $far = technicianOffering($this->service, -8.0300, 112.6150, ['business_name' => 'Jauh AC']);

    Livewire::test('pages::search', ['service' => 'cuci', 'radius' => 3])
        ->assertSee($near->business_name)
        ->assertDontSee($far->business_name);
});

it('lists only technicians with a verified badge', function () {
    technicianOffering($this->service, -7.9440, 112.6160, ['business_name' => 'Belum Diperiksa AC', 'badge' => TechnicianBadge::Terdaftar]);
    technicianOffering($this->service, -7.9441, 112.6161, ['business_name' => 'Identitas AC', 'badge' => TechnicianBadge::Identitas]);
    technicianOffering($this->service, -7.9442, 112.6162, ['business_name' => 'Sertifikat AC', 'badge' => TechnicianBadge::Sertifikat]);

    Livewire::test('pages::search', ['service' => 'cuci'])
        ->assertSee(['Identitas AC', 'Sertifikat AC'])
        ->assertDontSee('Belum Diperiksa AC');
});

it('filters to certified technicians', function () {
    technicianOffering($this->service, -7.9441, 112.6161, ['business_name' => 'Identitas AC', 'badge' => TechnicianBadge::Identitas]);
    technicianOffering($this->service, -7.9442, 112.6162, ['business_name' => 'Sertifikat AC', 'badge' => TechnicianBadge::Sertifikat]);

    Livewire::test('pages::search', ['service' => 'cuci'])
        ->set('badge', 'sertifikat')
        ->assertSee('Sertifikat AC')
        ->assertDontSee('Identitas AC');
});

it('hides technicians that do not offer the selected service', function () {
    $other = Service::factory()->create(['slug' => 'bongkar']);
    technicianOffering($other, -7.9440, 112.6160, ['business_name' => 'Spesialis Bongkar']);

    Livewire::test('pages::search', ['service' => 'cuci'])
        ->assertDontSee('Spesialis Bongkar');
});

it('shows only technicians available today when asked', function () {
    $this->travelTo(today()->setHour(9));

    technicianOffering($this->service, -7.9440, 112.6160, ['business_name' => 'Siap AC']);
    technicianOffering($this->service, -7.9441, 112.6161, ['business_name' => 'Libur AC', 'is_accepting_orders' => false]);

    Livewire::test('pages::search', ['service' => 'cuci'])
        ->set('availableToday', true)
        ->assertSee('Siap AC')
        ->assertDontSee('Libur AC');
});

it('does not list technicians awaiting verification', function () {
    $pending = technicianOffering($this->service, -7.9440, 112.6160, ['business_name' => 'Belum Diverifikasi AC']);
    $pending->update(['verification_status' => 'pending']);

    Livewire::test('pages::search', ['service' => 'cuci'])
        ->assertDontSee('Belum Diverifikasi AC');
});

it('skips technicians the customer hid after rejecting an estimate', function () {
    $hidden = technicianOffering($this->service, -7.9440, 112.6160, ['business_name' => 'Ditolak AC']);
    session(['sejuk.hidden_technicians' => [$hidden->id]]);

    Livewire::test('pages::search', ['service' => 'cuci'])
        ->assertDontSee('Ditolak AC');
});

it('suggests a wider radius when nothing is found', function () {
    technicianOffering($this->service, -7.9800, 112.6150, ['business_name' => 'Agak Jauh AC']);

    Livewire::test('pages::search', ['service' => 'cuci', 'radius' => 1])
        ->assertSee('Perluas radius ke 3 km')
        ->set('radius', 5)
        ->assertSee('Agak Jauh AC');
});
