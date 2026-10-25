<?php

use App\Models\TechnicianProfile;
use App\Models\User;

test('technicians can sign in with their phone number', function () {
    $user = User::factory()->technician()->create(['phone' => '081111000001']);
    TechnicianProfile::factory()->for($user)->create();

    $response = $this->post(route('login.store'), [
        'email' => '+62 811-1100-0001',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('mitra.dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('signing in with an unknown phone number fails', function () {
    $this->post(route('login.store'), [
        'email' => '089999999999',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('admins land on the admin portal after signing in', function () {
    $admin = User::factory()->admin()->create();

    $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect(route('admin.dashboard'));
});

test('customers cannot open the technician or admin portals', function (string $routeName) {
    $this->actingAs(User::factory()->create())
        ->get(route($routeName))
        ->assertForbidden();
})->with(['mitra.dashboard', 'admin.dashboard']);

test('technicians cannot open the admin portal', function () {
    $this->actingAs(User::factory()->technician()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('guests are sent to login from the technician portal', function () {
    $this->get(route('mitra.dashboard'))->assertRedirect(route('login'));
});
