<?php

use App\Models\AuditLog;
use App\Models\ContentPage;
use App\Models\Service;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Notifications\AccountNotice;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('keeps the new admin pages away from other roles', function (string $route) {
    $this->actingAs(User::factory()->customer()->create())->get(route($route))->assertForbidden();
})->with(['admin.users.index', 'admin.master', 'admin.content', 'admin.audit']);

it('warns an account and records it', function () {
    Notification::fake();
    $customer = User::factory()->customer()->create();

    Livewire::actingAs($this->admin)->test('pages::admin.users.show', ['user' => $customer])
        ->set('warningReason', 'Membatalkan pesanan berulang kali tanpa alasan.')
        ->call('warn')
        ->assertHasNoErrors();

    expect($customer->warnings()->sole()->admin_id)->toBe($this->admin->id)
        ->and(AuditLog::where('action', 'user.warned')->where('subject_id', $customer->id)->exists())->toBeTrue();
    Notification::assertSentTo($customer, AccountNotice::class);
});

it('suspends a technician, signs them out, and hides their profile until restored', function () {
    Notification::fake();
    $technician = TechnicianProfile::factory()->create();

    Livewire::actingAs($this->admin)->test('pages::admin.users.show', ['user' => $technician->user])
        ->set('suspensionReason', 'Menerima pembayaran tunai di luar aplikasi.')
        ->call('suspend')
        ->assertHasNoErrors();

    expect($technician->refresh()->is_active)->toBeFalse()
        ->and($technician->user->refresh()->isSuspended())->toBeTrue();

    $this->actingAs($technician->user)->get(route('mitra.dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();

    Livewire::actingAs($this->admin)->test('pages::admin.users.show', ['user' => $technician->user])->call('restore');

    expect($technician->refresh()->is_active)->toBeTrue()
        ->and($technician->user->refresh()->isSuspended())->toBeFalse();
});

it('does not open admin accounts for moderation', function () {
    $this->actingAs($this->admin)->get(route('admin.users.show', User::factory()->admin()->create()))->assertNotFound();
});

it('manages services with a reference price range and service areas', function () {
    $service = Service::factory()->create(['name' => 'Cuci AC']);

    Livewire::actingAs($this->admin)->test('pages::admin.master')
        ->call('editService', $service->id)
        ->set('priceMin', 75000)
        ->set('priceMax', 50000)
        ->call('saveService')
        ->assertHasErrors('priceMax')
        ->set('priceMax', 150000)
        ->call('saveService')
        ->assertHasNoErrors()
        ->set('newArea', 'Tunggulwulung')
        ->call('addArea')
        ->assertHasNoErrors()
        ->assertSee('Tunggulwulung');

    expect($service->refresh()->priceRangeLabel())->toBe(rupiah(75000).' – '.rupiah(150000))
        ->and(AuditLog::where('action', 'service.saved')->exists())->toBeTrue();
});

it('shows the default legal pages and the edited version once the admin saves it', function () {
    $this->get(route('pages.privacy'))->assertOk()->assertSee('Kebijakan Privasi');
    $this->get(route('pages.partnership'))->assertOk()->assertSee('mitra independen');

    Livewire::actingAs($this->admin)->test('pages::admin.content')
        ->call('choose', 'syarat-ketentuan')
        ->set('body', "## Ketentuan baru\n\nIsi baru <script>alert(1)</script>")
        ->call('save')
        ->assertHasNoErrors();

    $this->get(route('pages.terms'))
        ->assertOk()
        ->assertSee('Ketentuan baru')
        ->assertDontSee('<script>alert(1)</script>', false);

    expect(ContentPage::where('slug', 'syarat-ketentuan')->sole()->updated_by)->toBe($this->admin->id);
});

it('lists admin actions in the audit log', function () {
    AuditLog::record('user.warned', 'Peringatan untuk Dimas', actor: $this->admin);

    $this->actingAs($this->admin)->get(route('admin.audit'))->assertOk()->assertSee('Peringatan untuk Dimas');
});
