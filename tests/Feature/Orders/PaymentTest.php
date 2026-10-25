<?php

use App\Enums\FundStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

const SANDBOX = 'https://api.sandbox.midtrans.com';

beforeEach(function () {
    config(['services.midtrans.server_key' => 'SB-Mid-server-test', 'services.midtrans.is_production' => false]);
    Http::preventStrayRequests();

    $this->order = Order::factory()->withEstimate()->create(['status' => OrderStatus::MenungguPembayaran, 'final_total' => 550000]);
    $this->order->costItems()->create(['label' => 'Jasa perbaikan', 'amount' => 550000, 'is_additional' => false]);
    $this->actingAs($this->order->customer);
});

function signedNotification(Payment $payment, string $status, array $extra = []): array
{
    $notification = [
        'order_id' => $payment->gateway_ref,
        'status_code' => $status === 'settlement' ? '200' : '201',
        'gross_amount' => number_format($payment->amount, 2, '.', ''),
        'transaction_status' => $status,
        'transaction_id' => 'trx-123',
        'payment_type' => 'qris',
        ...$extra,
    ];
    $notification['signature_key'] = hash('sha512', $notification['order_id'].$notification['status_code'].$notification['gross_amount'].'SB-Mid-server-test');

    return $notification;
}

function pendingPayment(Order $order, PaymentMethod $method = PaymentMethod::Qris, array $attributes = []): Payment
{
    $payment = $order->payments()->create([
        'method' => $method,
        'amount' => $order->final_total,
        'status' => PaymentStatus::Pending,
        'expires_at' => now()->addMinutes(15),
        ...$attributes,
    ]);
    $payment->update(['gateway_ref' => $order->code.'-P'.$payment->id]);

    return $payment;
}

it('starts a QRIS payment that expires in 15 minutes', function () {
    Http::fake([SANDBOX.'/v2/charge' => Http::response([
        'status_code' => '201', 'transaction_id' => 'trx-1', 'transaction_status' => 'pending',
        'actions' => [['name' => 'generate-qr-code', 'method' => 'GET', 'url' => 'https://api.sandbox.midtrans.com/v2/qris/trx-1/qr-code']],
    ])]);

    Livewire::test('pages::orders.show', ['order' => $this->order])
        ->set('paymentMethod', 'qris')
        ->call('startPayment')
        ->assertSee('Bayar sebelum')
        ->assertSee('Scan kode QR ini');

    $payment = Payment::sole();

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->gateway_ref)->toBe($this->order->code.'-P'.$payment->id)
        ->and($payment->qr_url)->toContain('/qr-code')
        ->and($payment->expires_at->toDateTimeString())->toBe(now()->addMinutes(15)->toDateTimeString());

    Http::assertSent(fn (Request $request) => $request->url() === SANDBOX.'/v2/charge'
        && $request['payment_type'] === 'qris'
        && $request['transaction_details']['gross_amount'] === 550000
        && $request['custom_expiry']['expiry_duration'] === 15
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('SB-Mid-server-test:')));
})->freezeTime();

it('shows the virtual account number for bank transfers', function () {
    Http::fake([SANDBOX.'/v2/charge' => Http::response([
        'status_code' => '201', 'transaction_status' => 'pending', 'va_numbers' => [['bank' => 'bca', 'va_number' => '12345678901']],
    ])]);

    Livewire::test('pages::orders.show', ['order' => $this->order])
        ->set('paymentMethod', 'bca_va')
        ->call('startPayment')
        ->assertSee('1234 5678 901');

    Http::assertSent(fn (Request $request) => $request['bank_transfer']['bank'] === 'bca');
});

it('uses a company code and bill key for Mandiri', function () {
    Http::fake([SANDBOX.'/v2/charge' => Http::response(['status_code' => '201', 'transaction_status' => 'pending', 'bill_key' => '990011223344', 'biller_code' => '70012'])]);

    $payment = app(PaymentService::class)->start($this->order, PaymentMethod::MandiriVa);

    expect($payment->va_number)->toBe('990011223344')->and($payment->biller_code)->toBe('70012');
    Http::assertSent(fn (Request $request) => $request['payment_type'] === 'echannel');
});

it('charges OVO and DANA as QRIS', function (PaymentMethod $method) {
    Http::fake([SANDBOX.'/v2/charge' => Http::response(['status_code' => '201', 'transaction_status' => 'pending'])]);

    app(PaymentService::class)->start($this->order, $method);

    Http::assertSent(fn (Request $request) => $request['payment_type'] === 'qris');
})->with([PaymentMethod::Ovo, PaymentMethod::Dana]);

it('explains when Midtrans keys are missing', function () {
    config(['services.midtrans.server_key' => null]);

    Livewire::test('pages::orders.show', ['order' => $this->order])
        ->set('paymentMethod', 'qris')
        ->call('startPayment')
        ->assertSee('Bayar biaya final')
        ->assertDontSee('Bayar sebelum');

    expect(Payment::sole()->status)->toBe(PaymentStatus::Failed)
        ->and($this->order->refresh()->status)->toBe(OrderStatus::MenungguPembayaran);
});

it('refuses to charge an order that is not awaiting payment', function () {
    $this->order->update(['status' => OrderStatus::Terjadwal]);

    app(PaymentService::class)->start($this->order, PaymentMethod::Qris);
})->throws(InvalidOrderTransition::class);

it('starts the work and holds the funds when Midtrans confirms payment', function () {
    $payment = pendingPayment($this->order);

    $this->postJson(route('webhooks.midtrans'), signedNotification($payment, 'settlement'))->assertOk();
    $this->postJson(route('webhooks.midtrans'), signedNotification($payment, 'settlement'))->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($this->order->refresh()->status)->toBe(OrderStatus::Dikerjakan)
        ->and($this->order->fund_status)->toBe(FundStatus::Ditahan)
        ->and($this->order->statusLogs()->where('to_status', OrderStatus::Dikerjakan)->count())->toBe(1);
});

it('rejects notifications with a bad signature', function () {
    $payment = pendingPayment($this->order);
    $notification = [...signedNotification($payment, 'settlement'), 'signature_key' => str_repeat('0', 128)];

    $this->postJson(route('webhooks.midtrans'), $notification)->assertForbidden();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($this->order->refresh()->status)->toBe(OrderStatus::MenungguPembayaran);
});

it('does not settle when the paid amount differs', function () {
    $payment = pendingPayment($this->order);

    $this->postJson(route('webhooks.midtrans'), signedNotification($payment, 'settlement', ['gross_amount' => '1000.00']))->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($this->order->refresh()->status)->toBe(OrderStatus::MenungguPembayaran);
});

it('lets the customer pick another method after an expired or failed payment', function (string $gatewayStatus, string $message) {
    $payment = pendingPayment($this->order);

    $this->postJson(route('webhooks.midtrans'), signedNotification($payment, $gatewayStatus))->assertOk();

    expect($this->order->refresh()->status)->toBe(OrderStatus::MenungguPembayaran);

    $this->get(route('orders.show', $this->order))
        ->assertSee($message)
        ->assertSee('Tidak ada dana yang terpotong')
        ->assertSee('Teknisi menunggu di lokasi')
        ->assertSee('BCA Virtual Account');
})->with([
    'expired' => ['expire', 'Batas waktu pembayaran habis'],
    'denied' => ['deny', 'Pembayaran gagal'],
]);

it('expires an unpaid attempt after 15 minutes and cancels it at Midtrans', function () {
    $payment = pendingPayment($this->order);
    Http::fake([
        SANDBOX.'/v2/*/status' => Http::response(['status_code' => '201', 'transaction_status' => 'pending', 'gross_amount' => '550000.00']),
        SANDBOX.'/v2/*/cancel' => Http::response(['status_code' => '200', 'transaction_status' => 'cancel']),
    ]);

    $this->travel(16)->minutes();
    $this->artisan('payments:sync')->assertSuccessful();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Expired);
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/cancel'));
});

it('checks with Midtrans when the customer says they already paid', function () {
    $payment = pendingPayment($this->order);
    Http::fake([SANDBOX.'/v2/*/status' => Http::response(['status_code' => '200', 'transaction_status' => 'settlement', 'gross_amount' => '550000.00', 'transaction_id' => 'trx-9'])]);

    Livewire::test('pages::orders.show', ['order' => $this->order])
        ->call('checkPayment')
        ->assertSee('Sedang dikerjakan');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid);
});

it('cancels the current instructions when changing method', function () {
    $payment = pendingPayment($this->order);
    Http::fake([
        SANDBOX.'/v2/*/status' => Http::response(['status_code' => '201', 'transaction_status' => 'pending', 'gross_amount' => '550000.00']),
        SANDBOX.'/v2/*/cancel' => Http::response(['status_code' => '200']),
    ]);

    Livewire::test('pages::orders.show', ['order' => $this->order])
        ->call('changePaymentMethod')
        ->assertSee('Pilih metode pembayaran');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Cancelled);
});

it('honors a late settlement after the local deadline', function () {
    $payment = pendingPayment($this->order, attributes: ['status' => PaymentStatus::Expired]);

    $this->postJson(route('webhooks.midtrans'), signedNotification($payment, 'settlement'))->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->order->refresh()->status)->toBe(OrderStatus::Dikerjakan);
});

it('ignores a late expiry for a payment already confirmed', function () {
    $payment = pendingPayment($this->order);
    $this->postJson(route('webhooks.midtrans'), signedNotification($payment, 'settlement'));

    $this->postJson(route('webhooks.midtrans'), signedNotification($payment, 'expire'))->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid);
});
