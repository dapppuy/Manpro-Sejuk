<?php

use App\Actions\Technician\ReviewTechnicianVerification;
use App\Enums\OrderStatus;
use App\Enums\TechnicianBadge;
use App\Models\Order;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Notifications\OrderUpdated;
use App\Notifications\TechnicianVerificationReviewed;
use App\Services\OrderWorkflow;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->technician = TechnicianProfile::factory()->create();
    $this->order = Order::factory()->for($this->technician, 'technician')->offered()->create();
});

it('notifies the customer when the estimate arrives, not the technician who sent it', function () {
    Notification::fake();

    app(OrderWorkflow::class)->submitEstimate($this->order, $this->technician, 100000, 150000, now()->addDay(), $this->order->time_window, null);

    Notification::assertSentTo($this->order->customer, OrderUpdated::class, fn (OrderUpdated $notification) => $notification->title === 'Estimasi sudah masuk' && str_contains($notification->body, 'Rp100.000'));
    Notification::assertNotSentTo($this->technician->user, OrderUpdated::class);
});

it('notifies the technician of a new request', function () {
    Notification::fake();
    $customer = User::factory()->customer()->create();

    app(OrderWorkflow::class)->submit($customer, $this->technician, [
        'service_id' => $this->order->service_id, 'ac_capacity' => '1', 'ac_brand' => 'Sharp', 'address' => 'Jl. Kertoraharjo',
        'preferred_date' => now()->addDay()->toDateString(), 'time_window' => 'pagi',
    ]);

    Notification::assertSentTo($this->technician->user, OrderUpdated::class, fn (OrderUpdated $notification) => $notification->title === 'Pesanan baru masuk');
    Notification::assertNotSentTo($customer, OrderUpdated::class);
});

it('tells both sides when payment arrives through the gateway', function () {
    $this->order->update(['status' => OrderStatus::MenungguPembayaran, 'final_total' => 200000]);
    Notification::fake();

    app(OrderWorkflow::class)->markPaid($this->order->refresh());

    Notification::assertSentTo($this->order->customer, OrderUpdated::class, fn ($notification) => $notification->title === 'Pembayaran diterima');
    Notification::assertSentTo($this->technician->user, OrderUpdated::class, fn ($notification) => $notification->title === 'Pembayaran diterima, mulai kerjakan');
});

it('alerts admins about disputes', function () {
    $admin = User::factory()->admin()->create();
    $this->order->update(['status' => OrderStatus::MenungguKonfirmasi, 'rework_count' => 2]);
    Notification::fake();

    app(OrderWorkflow::class)->requestRework($this->order->refresh(), $this->order->customer, null);

    Notification::assertSentTo($admin, OrderUpdated::class);
});

it('tells the technician about the verification decision', function () {
    $pending = TechnicianProfile::factory()->pending()->create();
    Notification::fake();

    app(ReviewTechnicianVerification::class)->approve($pending, TechnicianBadge::Identitas, User::factory()->admin()->create());

    Notification::assertSentTo($pending->user, TechnicianVerificationReviewed::class, fn ($notification) => $notification->approved);
});

it('shows unread notifications in the bell and marks them read', function () {
    $this->actingAs($this->order->customer);
    $this->order->customer->notify(new OrderUpdated($this->order, 'Estimasi sudah masuk', 'Setujui untuk menjadwalkan.', route('orders.show', $this->order)));

    Livewire::test('notification-bell')
        ->assertSee('Estimasi sudah masuk')
        ->assertSet('unreadCount', 1)
        ->call('markAllRead')
        ->assertSet('unreadCount', 0);
});

it('opens the order from a notification', function () {
    $this->actingAs($this->order->customer);
    $this->order->customer->notify(new OrderUpdated($this->order, 'Judul', 'Isi', route('orders.show', $this->order)));
    $notification = $this->order->customer->notifications()->sole();

    Livewire::test('notification-bell')
        ->call('open', $notification->id)
        ->assertRedirect(route('orders.show', $this->order));

    expect($notification->refresh()->read_at)->not->toBeNull();
});
