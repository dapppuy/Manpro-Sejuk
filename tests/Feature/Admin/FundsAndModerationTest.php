<?php

use App\Enums\FundStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Review;
use App\Models\SearchLog;
use App\Models\TechnicianProfile;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->technician = TechnicianProfile::factory()->create(['business_name' => 'Budi Teknik AC', 'rating_avg' => 4.0, 'reviews_count' => 2]);
});

function forwardedOrder(TechnicianProfile $technician, int $total): Order
{
    return Order::factory()->for($technician, 'technician')->create([
        'status' => OrderStatus::Selesai,
        'final_total' => $total,
        'commission_amount' => (int) ($total * 0.1),
        'fund_status' => FundStatus::Diteruskan,
        'completed_at' => now(),
    ]);
}

it('records a payout for all forwarded orders of a technician', function () {
    $this->technician->payoutAccount()->create(['bank_name' => 'BCA', 'account_name' => 'Budi Santoso', 'account_number' => '1234567890']);
    $first = forwardedOrder($this->technician, 500000);
    $second = forwardedOrder($this->technician, 200000);
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.funds')
        ->assertSee('Rp630.000')
        ->set("references.{$this->technician->id}", 'TRF-001')
        ->call('markTransferred', $this->technician->id)
        ->assertSee('Tidak ada saldo yang menunggu pencairan');

    $payout = Payout::sole();

    expect($payout->amount)->toBe(630000)
        ->and($payout->reference)->toBe('TRF-001')
        ->and($payout->account_number)->toBe('1234567890')
        ->and($first->refresh()->fund_status)->toBe(FundStatus::Dicairkan)
        ->and($second->refresh()->payout_id)->toBe($payout->id);

    $this->actingAs($this->technician->user)
        ->get(route('mitra.earnings'))
        ->assertSee('Ref. TRF-001');
});

it('refuses a payout when the technician has no bank account', function () {
    forwardedOrder($this->technician, 500000);
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.funds')->call('markTransferred', $this->technician->id);

    expect(Payout::count())->toBe(0);
});

it('hides a review and adjusts the technician rating, then restores it', function () {
    $review = Review::factory()->for($this->technician, 'technician')->create(['rating' => 1, 'comment' => 'Kata kasar']);
    $this->technician->update(['rating_avg' => 3.0, 'reviews_count' => 3]);
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.reviews')->call('toggle', $review->id);

    expect($review->refresh()->is_hidden)->toBeTrue()
        ->and($this->technician->refresh()->reviews_count)->toBe(2)
        ->and($this->technician->rating_avg)->toBe(4.0);

    $this->get(route('technicians.show', $this->technician))->assertDontSee('Kata kasar');

    Livewire::test('pages::admin.reviews')->call('toggle', $review->id);

    expect($this->technician->refresh()->reviews_count)->toBe(3)
        ->and($this->technician->rating_avg)->toBe(3.0);
});

it('promotes a technician for a limited time', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.technicians.index')->call('setPromotion', $this->technician->id, 7);

    expect($this->technician->refresh()->isPromotedNow())->toBeTrue()
        ->and($this->technician->promoted_until->isSameDay(now()->addDays(7)))->toBeTrue();

    Livewire::test('pages::admin.technicians.index')->call('setPromotion', $this->technician->id, 0);

    expect($this->technician->refresh()->isPromotedNow())->toBeFalse();
});

it('shows conversion KPIs on the admin dashboard', function () {
    SearchLog::create(['results_count' => 3]);
    SearchLog::create(['results_count' => 0]);
    $order = Order::factory()->create(['status' => OrderStatus::Selesai, 'final_total' => 200000, 'estimate_max' => 180000, 'warranty' => true, 'warranty_fee' => 15000]);
    foreach ([OrderStatus::MenungguRespons, OrderStatus::Terjadwal, OrderStatus::Dikerjakan, OrderStatus::Selesai] as $status) {
        $order->statusLogs()->create(['to_status' => $status]);
    }
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.dashboard')
        ->assertSee('Konversi')
        ->assertSeeInOrder(['Pencarian', '2', 'Permintaan', '1', 'Estimasi disetujui', '1', 'Dibayar', '1', 'Selesai', '1'])
        ->assertSee('+Rp5.000')
        ->assertSee('100%');
});

it('logs each visit to the search page', function () {
    $this->get(route('search'))->assertOk();

    expect(SearchLog::count())->toBe(1);
});
