<?php

use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;
use Livewire\Livewire;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

test('registering a customer sends a verification email', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'phone' => '081234567890',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    Notification::assertSentTo(User::where('email', 'test@example.com')->sole(), VerifyEmail::class);
});

test('unverified users are sent to the verification notice from their portal', function (string $state, string $routeName) {
    $user = User::factory()->unverified()->{$state}()->create();

    if ($state === 'technician') {
        TechnicianProfile::factory()->for($user)->create();
    }

    $this->actingAs($user)->get(route($routeName))->assertRedirect(route('verification.notice'));
})->with([
    'customer' => ['customer', 'orders.index'],
    'technician' => ['technician', 'mitra.dashboard'],
    'admin' => ['admin', 'admin.dashboard'],
]);

test('verified users reach their portal', function () {
    $this->actingAs(User::factory()->customer()->create())->get(route('orders.index'))->assertOk();
});

test('the verification notice shows where the link was sent', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('verification.notice'))
        ->assertOk()
        ->assertSee($user->email);
});

test('the verification link verifies the email and opens the portal', function () {
    $user = User::factory()->unverified()->customer()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

    $this->actingAs($user)->get($url)->assertRedirect(route('dashboard').'?verified=1');

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});

test('a link with the wrong email hash does not verify', function () {
    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1('lain@example.com')]);

    $this->actingAs($user)->get($url)->assertForbidden();

    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

test('the verification email can be sent again', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->post(route('verification.send'))
        ->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('changing the email on the account page asks to verify it again', function () {
    Notification::fake();
    $user = User::factory()->customer()->create(['phone' => '081234567890']);

    Livewire::actingAs($user)->test('pages::account')
        ->call('edit')
        ->set('email', 'baru@example.com')
        ->call('saveProfile')
        ->assertRedirect(route('verification.notice'));

    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});
