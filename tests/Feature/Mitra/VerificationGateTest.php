<?php

use App\Models\TechnicianProfile;

it('sends a pending technician to the verification page', function (string $routeName) {
    $technician = TechnicianProfile::factory()->pending()->create();

    $this->actingAs($technician->user)
        ->get(route($routeName))
        ->assertRedirect(route('mitra.verification'));
})->with(['mitra.dashboard', 'mitra.profile', 'mitra.prices']);

it('shows the rejection reason to a rejected technician', function () {
    $technician = TechnicianProfile::factory()->pending()->create([
        'verification_status' => 'rejected',
        'verification_note' => 'Foto KTP buram, mohon kirim ulang.',
    ]);

    $this->actingAs($technician->user)
        ->get(route('mitra.verification'))
        ->assertOk()
        ->assertSee('Foto KTP buram, mohon kirim ulang.');
});

it('sends an approved technician from the verification page to the dashboard', function () {
    $technician = TechnicianProfile::factory()->create();

    $this->actingAs($technician->user)
        ->get(route('mitra.verification'))
        ->assertRedirect(route('mitra.dashboard'));
});
