<?php

use App\Models\Service;
use App\Models\TechnicianPrice;
use App\Models\TechnicianProfile;
use Livewire\Livewire;

beforeEach(function () {
    $this->service = Service::factory()->create(['slug' => 'cuci']);
    $this->technician = TechnicianProfile::factory()->create();
    $this->technician->services()->attach($this->service);

    $this->actingAs($this->technician->user);
});

it('adds a reference price row to an offered service', function () {
    Livewire::test('pages::mitra.prices')
        ->set("drafts.{$this->service->id}.note", '1,5–2 PK')
        ->set("drafts.{$this->service->id}.price_min", 85000)
        ->set("drafts.{$this->service->id}.price_max", 110000)
        ->call('addRow', $this->service->id)
        ->assertHasNoErrors();

    expect($this->technician->prices()->sole())
        ->note->toBe('1,5–2 PK')
        ->price_min->toBe(85000)
        ->price_max->toBe(110000);
});

it('rejects a maximum below the minimum', function () {
    Livewire::test('pages::mitra.prices')
        ->set("drafts.{$this->service->id}.note", '0,5–1 PK')
        ->set("drafts.{$this->service->id}.price_min", 90000)
        ->set("drafts.{$this->service->id}.price_max", 60000)
        ->call('addRow', $this->service->id)
        ->assertHasErrors(["drafts.{$this->service->id}.price_max" => 'gte']);

    expect($this->technician->prices()->count())->toBe(0);
});

it('forbids adding prices for a service the technician does not offer', function () {
    $other = Service::factory()->create();

    Livewire::test('pages::mitra.prices')
        ->call('addRow', $other->id)
        ->assertForbidden();
});

it('updates and deletes the technician own rows', function () {
    $price = $this->technician->prices()->create(['service_id' => $this->service->id, 'note' => '0,5–1 PK', 'price_min' => 60000, 'price_max' => 85000]);

    Livewire::test('pages::mitra.prices')
        ->set("rows.{$price->id}.price_max", 90000)
        ->call('saveRow', $price->id)
        ->assertHasNoErrors()
        ->call('deleteRow', $price->id);

    expect(TechnicianPrice::find($price->id))->toBeNull();
});

it('cannot edit another technician price', function () {
    $foreign = TechnicianPrice::factory()->create();

    Livewire::test('pages::mitra.prices')
        ->call('saveRow', $foreign->id)
        ->assertNotFound();
});
