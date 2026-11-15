<?php

use App\Enums\TechnicianBadge;
use App\Models\Review;
use App\Models\Service;
use App\Models\TechnicianProfile;
use App\Models\User;

it('renders prices and visible reviews of a published technician', function () {
    $service = Service::factory()->create(['name' => 'Cuci AC']);
    $technician = TechnicianProfile::factory()->create(['business_name' => 'Budi Teknik AC']);
    $technician->prices()->create(['service_id' => $service->id, 'note' => '1,5–2 PK', 'price_min' => 85000, 'price_max' => 100000]);
    Review::factory()->for($technician, 'technician')->create(['comment' => 'Datang tepat waktu']);
    Review::factory()->for($technician, 'technician')->create(['comment' => 'Ulasan kasar', 'is_hidden' => true]);

    $this->get(route('technicians.show', $technician))
        ->assertOk()
        ->assertSee('Budi Teknik AC')
        ->assertSee('Rp85.000 – Rp100.000')
        ->assertSee('Datang tepat waktu')
        ->assertDontSee('Ulasan kasar');
});

it('returns 404 for a technician awaiting verification', function () {
    $technician = TechnicianProfile::factory()->pending()->create();

    $this->get(route('technicians.show', $technician))->assertNotFound();
});

it('hides a technician without a verified badge from the profile and booking pages', function () {
    $technician = TechnicianProfile::factory()->badge(TechnicianBadge::Terdaftar)->create();

    $this->get(route('technicians.show', $technician))->assertNotFound();
    $this->actingAs(User::factory()->customer()->create())->get(route('requests.create', $technician))->assertNotFound();
});

it('returns 404 for a deactivated technician', function () {
    $technician = TechnicianProfile::factory()->create(['is_active' => false]);

    $this->get(route('technicians.show', $technician))->assertNotFound();
});
