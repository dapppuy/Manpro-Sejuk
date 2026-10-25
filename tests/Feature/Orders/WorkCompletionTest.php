<?php

use App\Enums\FundStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\TechnicianProfile;
use App\Models\WorkProof;
use App\Services\OrderWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');

    $this->technician = TechnicianProfile::factory()->create(['jobs_count' => 10]);
    $this->order = Order::factory()->for($this->technician, 'technician')->withEstimate()->create([
        'status' => OrderStatus::Dikerjakan,
        'final_total' => 550000,
        'fund_status' => FundStatus::Ditahan,
    ]);
    $this->order->costItems()->create(['label' => 'Jasa perbaikan', 'amount' => 550000, 'is_additional' => false]);
    $this->order->payments()->create(['method' => PaymentMethod::Qris, 'amount' => 550000, 'status' => PaymentStatus::Paid, 'paid_at' => now()]);
});

function photo(string $name): UploadedFile
{
    return UploadedFile::fake()->create($name, 200, 'image/jpeg');
}

function submitProof(Order $order, TechnicianProfile $technician): void
{
    app(OrderWorkflow::class)->submitProof($order, $technician, photo('sebelum.jpg'), photo('sesudah.jpg'), 'Freon diisi, kapasitor diganti.');
}

it('lets the technician upload before and after photos and mark the job done', function () {
    $this->actingAs($this->technician->user);

    Livewire::test('pages::mitra.orders.proof', ['order' => $this->order])
        ->set('before', photo('sebelum.jpg'))
        ->set('after', photo('sesudah.jpg'))
        ->set('note', 'Freon diisi ulang.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('mitra.orders.show', $this->order));

    $proof = WorkProof::sole();

    expect($this->order->refresh()->status)->toBe(OrderStatus::MenungguKonfirmasi)
        ->and($this->order->proof_submitted_at)->not->toBeNull()
        ->and($proof->round)->toBe(1)
        ->and($proof->note)->toBe('Freon diisi ulang.');

    Storage::disk('local')->assertExists([$proof->before_path, $proof->after_path]);
});

it('requires both photos', function () {
    $this->actingAs($this->technician->user);

    Livewire::test('pages::mitra.orders.proof', ['order' => $this->order])
        ->set('before', photo('sebelum.jpg'))
        ->call('submit')
        ->assertHasErrors(['after' => 'required']);
});

it('does not accept proof before the customer has paid', function () {
    $this->actingAs($this->technician->user);
    $this->order->update(['status' => OrderStatus::MenungguPembayaran]);

    $this->get(route('mitra.orders.proof', $this->order))->assertRedirect(route('mitra.orders.show', $this->order));
});

it('shows the proof and checklist to the customer', function () {
    submitProof($this->order, $this->technician);

    $this->actingAs($this->order->customer)
        ->get(route('orders.show', $this->order))
        ->assertSee('Cek dulu sebelum menandai selesai')
        ->assertSee('AC menyala dan dingin')
        ->assertSee('Freon diisi, kapasitor diganti.')
        ->assertSee($this->order->workProofs()->first()->photoUrl('sebelum'));
});

it('completes the order and forwards the funds minus commission when the customer confirms', function () {
    submitProof($this->order, $this->technician);
    $this->actingAs($this->order->customer);

    Livewire::test('pages::orders.show', ['order' => $this->order->refresh()])
        ->call('confirmCompletion')
        ->assertSee('AC kamu sudah beres')
        ->assertSee('Lunas')
        ->assertSee('QRIS');

    expect($this->order->refresh())
        ->status->toBe(OrderStatus::Selesai)
        ->fund_status->toBe(FundStatus::Diteruskan)
        ->commission_amount->toBe(55000)
        ->completed_at->not->toBeNull()
        ->and($this->order->technicianPayout())->toBe(495000)
        ->and($this->technician->refresh()->jobs_count)->toBe(11);
});

it('sends the job back for a free rework with the customer complaint', function () {
    submitProof($this->order, $this->technician);
    $this->actingAs($this->order->customer);

    Livewire::test('pages::orders.show', ['order' => $this->order->refresh()])
        ->call('$set', 'showReworkForm', true)
        ->set('complaint', 'Masih ada tetesan air.')
        ->call('requestRework')
        ->assertSee('Menunggu perbaikan ulang');

    expect($this->order->refresh())
        ->status->toBe(OrderStatus::PerbaikanUlang)
        ->rework_count->toBe(1)
        ->fund_status->toBe(FundStatus::Ditahan)
        ->and(WorkProof::sole()->complaint)->toBe('Masih ada tetesan air.');

    $this->actingAs($this->technician->user)
        ->get(route('mitra.orders.proof', $this->order))
        ->assertSee('Masih ada tetesan air.');

    submitProof($this->order->refresh(), $this->technician);

    $this->actingAs($this->order->customer)
        ->get(route('orders.show', $this->order))
        ->assertSee('Bukti baru setelah perbaikan ulang');

    expect($this->order->workProofs()->pluck('round')->all())->toBe([1, 2]);
});

it('sends the order to admin review after too many reworks', function () {
    $workflow = app(OrderWorkflow::class);

    foreach (range(1, 3) as $round) {
        submitProof($this->order->refresh(), $this->technician);
        $workflow->requestRework($this->order->refresh(), $this->order->customer, null);
    }

    expect($this->order->refresh()->status)->toBe(OrderStatus::Sengketa)
        ->and($this->order->rework_count)->toBe(3)
        ->and($this->order->fund_status)->toBe(FundStatus::Ditahan);
});

it('completes automatically 24 hours after the proof without confirmation', function () {
    submitProof($this->order, $this->technician);

    $this->travel(23)->hours();
    $this->artisan('orders:auto-complete')->assertSuccessful();
    expect($this->order->refresh()->status)->toBe(OrderStatus::MenungguKonfirmasi);

    $this->travel(2)->hours();
    $this->artisan('orders:auto-complete')->assertSuccessful();

    expect($this->order->refresh())
        ->status->toBe(OrderStatus::Selesai)
        ->fund_status->toBe(FundStatus::Diteruskan)
        ->and($this->order->statusLogs()->latest('id')->value('note'))->toContain('otomatis');
});

it('shows proof photos only to people on the order', function () {
    submitProof($this->order, $this->technician);
    $proof = WorkProof::sole();

    $this->actingAs($this->order->customer)->get($proof->photoUrl('sesudah'))->assertOk();
    $this->actingAs($this->technician->user)->get($proof->photoUrl('sebelum'))->assertOk();
    $this->actingAs(TechnicianProfile::factory()->create()->user)->get($proof->photoUrl('sebelum'))->assertForbidden();
    $this->actingAs($this->order->customer)->get(route('orders.proofs.photo', ['proof' => $proof, 'side' => 'lain']))->assertNotFound();
});

it('shows the technician the forwarded amount after completion', function () {
    submitProof($this->order, $this->technician);
    app(OrderWorkflow::class)->confirmCompletion($this->order->refresh(), $this->order->customer);

    $this->actingAs($this->technician->user)
        ->get(route('mitra.orders.show', $this->order))
        ->assertSee('Dana diteruskan: Rp495.000');
});
