<?php

use App\Models\Service;
use App\Models\TechnicianProfile;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->cuci = Service::factory()->create(['slug' => 'cuci']);
    $this->bongkar = Service::factory()->create(['slug' => 'bongkar']);

    $this->technician = TechnicianProfile::factory()->create();
    $this->technician->services()->attach([$this->cuci->id, $this->bongkar->id]);
    $this->technician->prices()->create(['service_id' => $this->bongkar->id, 'note' => 'Bongkar + pasang', 'price_min' => 300000, 'price_max' => 450000]);
    $this->technician->serviceAreas()->create(['area_name' => 'Lowokwaru']);

    $this->actingAs($this->technician->user);
});

it('saves profile details, brands, and service areas', function () {
    Livewire::test('pages::mitra.profile')
        ->call('edit')
        ->set('business_name', 'Budi Sejuk Teknik')
        ->set('phone', '+62 812-0000-1111')
        ->set('bio', 'Spesialis AC inverter.')
        ->set('brands', ['Daikin', 'LG'])
        ->set('areas', 'Lowokwaru, Dinoyo, Lowokwaru')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editing', false);

    $this->technician->refresh();

    expect($this->technician->business_name)->toBe('Budi Sejuk Teknik')
        ->and($this->technician->user->phone)->toBe('081200001111')
        ->and($this->technician->bio)->toBe('Spesialis AC inverter.')
        ->and($this->technician->brands->pluck('brand')->all())->toBe(['Daikin', 'LG'])
        ->and($this->technician->serviceAreas->pluck('area_name')->all())->toBe(['Lowokwaru', 'Dinoyo']);
});

it('removes reference prices of services the technician stops offering', function () {
    Livewire::test('pages::mitra.profile')
        ->call('edit')
        ->set('service_ids', [$this->cuci->id])
        ->call('save')
        ->assertHasNoErrors();

    expect($this->technician->services()->pluck('services.id')->all())->toBe([$this->cuci->id])
        ->and($this->technician->prices()->count())->toBe(0);
});

it('rejects a phone number used by another account', function () {
    User::factory()->create(['phone' => '081299998888']);

    Livewire::test('pages::mitra.profile')
        ->call('edit')
        ->set('phone', '081299998888')
        ->call('save')
        ->assertHasErrors(['phone' => 'unique']);
});

it('requires at least one service', function () {
    Livewire::test('pages::mitra.profile')
        ->call('edit')
        ->set('service_ids', [])
        ->call('save')
        ->assertHasErrors(['service_ids' => 'required']);
});
