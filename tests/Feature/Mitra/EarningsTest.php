<?php

use App\Enums\FundStatus;
use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\TechnicianProfile;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 20)->setTime(10, 0));
    $this->technician = TechnicianProfile::factory()->create();
    $this->actingAs($this->technician->user);
});

function finishedOrder(TechnicianProfile $technician, int $total, $completedAt, FundStatus $fund = FundStatus::Diteruskan): Order
{
    return Order::factory()->for($technician, 'technician')->offered(OfferStatus::Diterima)->create([
        'status' => OrderStatus::Selesai,
        'final_total' => $total,
        'commission_amount' => (int) ($total * 0.1),
        'fund_status' => $fund,
        'completed_at' => $completedAt,
    ]);
}

it('shows this month earnings against last month and the completion rate', function () {
    finishedOrder($this->technician, 500000, now()->subDays(3));
    finishedOrder($this->technician, 200000, now()->subMonth());
    Order::factory()->for($this->technician, 'technician')->offered(OfferStatus::Diterima)->create(['status' => OrderStatus::Dibatalkan]);
    Order::factory()->for($this->technician, 'technician')->offered()->create(['status' => OrderStatus::MenungguRespons]);

    Livewire::test('pages::mitra.dashboard')
        ->assertSee('Rp450.000')
        ->assertSee('+150% dari bulan lalu')
        ->assertSee('1 perlu tindakan')
        ->assertSee('67%')
        ->assertSee('2 dari 3 pesanan');
});

it('splits balances into held, ready to pay out, and paid out', function () {
    Order::factory()->for($this->technician, 'technician')->create(['status' => OrderStatus::Dikerjakan, 'final_total' => 300000, 'fund_status' => FundStatus::Ditahan]);
    finishedOrder($this->technician, 500000, now());
    finishedOrder($this->technician, 100000, now()->subWeek(), FundStatus::Dicairkan);

    Livewire::test('pages::mitra.earnings')
        ->assertSee('Rp270.000')
        ->assertSee('Rp450.000')
        ->assertSee('Rp90.000');
});

it('saves the payout bank account', function () {
    Livewire::test('pages::mitra.earnings')
        ->call('editAccount')
        ->set('bank_name', 'BCA')
        ->set('account_name', 'Budi Santoso')
        ->set('account_number', '1234-5678-90')
        ->call('saveAccount')
        ->assertHasNoErrors()
        ->assertSee('••••••7890');

    expect($this->technician->payoutAccount()->sole()->account_number)->toBe('1234567890');
});

it('rejects an invalid account number', function () {
    Livewire::test('pages::mitra.earnings')
        ->call('editAccount')
        ->set('bank_name', 'BCA')
        ->set('account_number', '12')
        ->call('saveAccount')
        ->assertHasErrors(['account_number']);
});
