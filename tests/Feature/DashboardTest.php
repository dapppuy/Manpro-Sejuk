<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('dashboard sends each role to its own portal', function (string $state, string $routeName) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route($routeName));
})->with([
    'customer' => ['customer', 'home'],
    'technician' => ['technician', 'mitra.dashboard'],
    'admin' => ['admin', 'admin.dashboard'],
]);
