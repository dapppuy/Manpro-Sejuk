<?php

use App\Enums\FundStatus;
use App\Enums\OrderStatus;
use App\Models\CustomerUnit;
use App\Models\Order;
use App\Models\Service;
use App\Models\TechnicianProfile;
use App\Models\UnitIssueReport;
use App\Models\UnitServiceLog;
use App\Models\User;
use App\Notifications\AcUnitNotice;
use App\Services\OrderWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');

    $this->owner = User::factory()->customer()->create(['name' => 'Bu Kos Rahma']);
    $this->unit = CustomerUnit::factory()->kos('Kamar A-03')->for($this->owner)->create(['name' => 'AC kamar depan']);
});

it('registers an AC unit with its own QR code', function () {
    Livewire::actingAs($this->owner)->test('pages::units.index')
        ->call('newUnit')
        ->set('name', 'AC ruang tamu')
        ->set('brand', 'Daikin')
        ->set('capacity_pk', '1.5')
        ->set('sticker', UploadedFile::fake()->create('stiker.jpg', 100, 'image/jpeg'))
        ->call('saveUnit')
        ->assertHasNoErrors()
        ->assertSee('AC ruang tamu');

    $unit = $this->owner->customerUnits()->where('name', 'AC ruang tamu')->sole();

    expect($unit->qr_token)->not->toBeEmpty()
        ->and($unit->specLine())->toBe('Daikin · 1,5 PK · Split dinding');
    Storage::disk('local')->assertExists($unit->sticker_photo_path);
});

it('keeps units and their photos private to the owner', function () {
    $stranger = User::factory()->customer()->create();

    $this->actingAs($stranger)->get(route('units.show', $this->unit))->assertNotFound();
    Livewire::actingAs($stranger)->test('pages::units.index')->call('editUnit', $this->unit->id)->assertNotFound();

    $this->unit->update(['sticker_photo_path' => UploadedFile::fake()->create('s.jpg', 10, 'image/jpeg')->store('unit-stickers', 'local')]);
    $this->actingAs($stranger)->get(route('units.photo', [$this->unit, 'stiker']))->assertForbidden();
    $this->actingAs($this->owner)->get(route('units.photo', [$this->unit, 'stiker']))->assertOk();
});

it('shows the service history on the public QR page without owner details', function () {
    $technician = TechnicianProfile::factory()->create(['business_name' => 'Budi Teknik AC']);
    UnitServiceLog::factory()->for($this->unit, 'unit')->create(['technician_id' => $technician->user_id, 'actions_taken' => 'Cuci indoor dan outdoor']);
    $pendingOrder = Order::factory()->for($technician, 'technician')->create(['status' => OrderStatus::MenungguKonfirmasi]);
    UnitServiceLog::factory()->for($this->unit, 'unit')->create(['order_id' => $pendingOrder->id, 'actions_taken' => 'Belum dikonfirmasi pelanggan']);

    $this->get(route('unit.qr.show', $this->unit->qr_token))
        ->assertOk()
        ->assertSee(['AC kamar depan', 'Kamar A-03', 'Budi Teknik AC', 'Cuci indoor dan outdoor'])
        ->assertDontSee(['Belum dikonfirmasi pelanggan', 'Bu Kos Rahma']);
});

it('lets a tenant report a problem through the QR page and notifies the owner', function () {
    Notification::fake();

    Livewire::test('pages::units.qr', ['qrToken' => $this->unit->qr_token])
        ->set('reporter_name', 'Dina')
        ->set('issue_description', 'AC menetes air sejak kemarin malam.')
        ->call('report')
        ->assertHasNoErrors()
        ->assertSet('reported', true);

    expect($this->unit->issueReports()->sole())
        ->reporter_name->toBe('Dina')
        ->status->toBe(UnitIssueReport::STATUS_WAITING);
    Notification::assertSentTo($this->owner, AcUnitNotice::class);
});

it('ties an order to the chosen unit and marks waiting reports as ordered', function () {
    Http::preventStrayRequests();
    Http::fake(['nominatim.openstreetmap.org/search*' => Http::response([['lat' => '-7.9455', 'lon' => '112.6150']])]);
    $this->travelTo(now()->setTime(9, 0));
    $service = Service::factory()->create();
    $technician = TechnicianProfile::factory()->create();
    $technician->services()->attach($service);
    $report = $this->unit->issueReports()->create(['reporter_name' => 'Dina', 'issue_description' => 'AC menetes air.', 'status' => UnitIssueReport::STATUS_WAITING]);

    $this->actingAs($this->owner)->withSession(['sejuk.order_unit' => $this->unit->id]);

    Livewire::test('pages::requests.create', ['technician' => $technician])
        ->assertSet('customer_unit_id', $this->unit->id)
        ->set('ac_capacity', '1')
        ->set('ac_brand', 'Sharp')
        ->set('address', 'Jl. Kertoraharjo')
        ->set('day', 1)
        ->set('time_window', 'pagi')
        ->call('submit')
        ->assertHasNoErrors();

    $order = Order::sole();

    expect($order->customer_unit_id)->toBe($this->unit->id)
        ->and($report->refresh()->status)->toBe(UnitIssueReport::STATUS_ORDERED)
        ->and($report->order_id)->toBe($order->id);
});

it('refuses a unit that belongs to another customer on the request form', function () {
    $service = Service::factory()->create();
    $technician = TechnicianProfile::factory()->create();
    $technician->services()->attach($service);

    Livewire::actingAs(User::factory()->customer()->create())->test('pages::requests.create', ['technician' => $technician])
        ->set('customer_unit_id', $this->unit->id)
        ->call('submit')
        ->assertHasErrors('customer_unit_id');
});

it('records the technician notes in the unit history once the order is completed', function () {
    $technician = TechnicianProfile::factory()->create(['business_name' => 'Budi Teknik AC']);
    $order = Order::factory()->for($technician, 'technician')->for($this->owner, 'customer')->withEstimate()->create([
        'status' => OrderStatus::Dikerjakan,
        'final_total' => 150000,
        'fund_status' => FundStatus::Ditahan,
        'customer_unit_id' => $this->unit->id,
    ]);

    Livewire::actingAs($technician->user)->test('pages::mitra.orders.proof', ['order' => $order])
        ->set('before', UploadedFile::fake()->create('sebelum.jpg', 100, 'image/jpeg'))
        ->set('after', UploadedFile::fake()->create('sesudah.jpg', 100, 'image/jpeg'))
        ->set('findings', 'Filter berdebu tebal.')
        ->set('actions', 'Cuci indoor, bersihkan filter.')
        ->set('nextServiceMonths', 3)
        ->call('submit')
        ->assertHasNoErrors();

    $log = UnitServiceLog::sole();

    expect($log->order_id)->toBe($order->id)
        ->and($log->next_recommended_service_date->toDateString())->toBe(today()->addMonths(3)->toDateString())
        ->and($this->unit->completedServiceLogs()->count())->toBe(0);

    app(OrderWorkflow::class)->confirmCompletion($order->refresh(), $this->owner);

    expect($this->unit->refresh()->completedServiceLogs()->count())->toBe(1)
        ->and($this->unit->last_serviced_at->isToday())->toBeTrue();
});

it('asks the technician for the work done when the order has a unit', function () {
    $technician = TechnicianProfile::factory()->create();
    $order = Order::factory()->for($technician, 'technician')->withEstimate()->create([
        'status' => OrderStatus::Dikerjakan,
        'customer_unit_id' => $this->unit->id,
    ]);

    Livewire::actingAs($technician->user)->test('pages::mitra.orders.proof', ['order' => $order])
        ->set('before', UploadedFile::fake()->create('sebelum.jpg', 100, 'image/jpeg'))
        ->set('after', UploadedFile::fake()->create('sesudah.jpg', 100, 'image/jpeg'))
        ->call('submit')
        ->assertHasErrors('actions');
});

it('reminds the owner once when the next service is due', function () {
    Notification::fake();
    $due = UnitServiceLog::factory()->for($this->unit, 'unit')->create(['next_recommended_service_date' => today()]);
    UnitServiceLog::factory()->for(CustomerUnit::factory()->for($this->owner), 'unit')->create(['next_recommended_service_date' => today()->addWeek()]);

    $this->artisan('units:send-service-reminders')->assertSuccessful();
    $this->artisan('units:send-service-reminders')->assertSuccessful();

    Notification::assertSentToTimes($this->owner, AcUnitNotice::class, 1);
    expect($due->refresh()->reminder_sent_at)->not->toBeNull();
});
