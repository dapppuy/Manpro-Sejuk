<?php

use App\Enums\OrderStatus;
use App\Enums\TimeWindow;
use App\Models\Order;
use App\Notifications\OrderVisitReminder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->travelTo(now()->setTime(10, 0));

    $this->order = Order::factory()->withEstimate()->create([
        'status' => OrderStatus::Terjadwal,
        'scheduled_date' => today()->addDay()->toDateString(),
        'scheduled_window' => TimeWindow::Pagi,
    ]);
});

it('reminds the customer and the technician a day before the visit, once', function () {
    Notification::fake();

    $this->artisan('orders:send-reminders')->assertSuccessful();
    $this->artisan('orders:send-reminders')->assertSuccessful();

    Notification::assertSentToTimes($this->order->customer, OrderVisitReminder::class, 1);
    Notification::assertSentTo($this->order->technician->user, OrderVisitReminder::class, fn (OrderVisitReminder $reminder) => $reminder->timing === OrderVisitReminder::DAY_BEFORE);

    expect($this->order->refresh()->reminded_day_before_at)->not->toBeNull()
        ->and($this->order->reminded_hour_before_at)->toBeNull();
});

it('reminds again an hour before the visit', function () {
    $this->order->update(['reminded_day_before_at' => now()]);
    $this->travelTo(today()->addDay()->setTime(7, 10));
    Notification::fake();

    $this->artisan('orders:send-reminders')->assertSuccessful();

    Notification::assertSentTo($this->order->customer, OrderVisitReminder::class, fn (OrderVisitReminder $reminder) => $reminder->timing === OrderVisitReminder::HOUR_BEFORE);
    expect($this->order->refresh()->reminded_hour_before_at)->not->toBeNull();
});

it('does not remind for visits more than a day away or orders no longer scheduled', function () {
    $this->order->update(['scheduled_date' => today()->addDays(2)->toDateString()]);
    Order::factory()->withEstimate()->create(['status' => OrderStatus::Dibatalkan, 'scheduled_date' => today()->addDay()->toDateString()]);
    Notification::fake();

    $this->artisan('orders:send-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});

it('renders the reminder email in Indonesian with a link to the order', function () {
    $mail = (new OrderVisitReminder($this->order, OrderVisitReminder::DAY_BEFORE))->toMail($this->order->customer);

    expect($mail->subject)->toBe('Pengingat: teknisi datang besok')
        ->and($mail->actionUrl)->toBe(route('orders.show', $this->order));
});
