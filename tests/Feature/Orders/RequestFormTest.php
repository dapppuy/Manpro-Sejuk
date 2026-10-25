<?php

use App\Enums\MediaType;
use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Service;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(['nominatim.openstreetmap.org/search*' => Http::response([['lat' => '-7.9455', 'lon' => '112.6150']])]);
    Storage::fake('local');
    $this->travelTo(now()->setTime(9, 0));

    $this->service = Service::factory()->create(['slug' => 'perbaikan', 'name' => 'Perbaikan']);
    $this->technician = TechnicianProfile::factory()->create();
    $this->technician->services()->attach($this->service);
    $this->customer = User::factory()->customer()->create();
});

function fillRequest(TechnicianProfile $technician, array $query = []): Testable
{
    return Livewire::withQueryParams($query)->test('pages::requests.create', ['technician' => $technician])
        ->set('ac_capacity', '1')
        ->set('ac_brand', 'Sharp')
        ->set('address', 'Jl. Kertoraharjo, Ketawanggede')
        ->set('address_detail', 'Kos Melati, kamar 12')
        ->set('day', 1)
        ->set('time_window', 'pagi')
        ->set('notes', 'AC tidak dingin sejak dua hari lalu.');
}

it('redirects guests to log in before requesting a quote', function () {
    $this->get(route('requests.create', $this->technician))->assertRedirect(route('login'));
});

it('sends a quote request with damage photos to the technician', function () {
    $this->actingAs($this->customer);

    fillRequest($this->technician)
        ->set('warranty', true)
        ->set('newMedia', [UploadedFile::fake()->create('unit.jpg', 300, 'image/jpeg'), UploadedFile::fake()->create('bunyi.mp4', 900, 'video/mp4')])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('orders.show', Order::sole()));

    $order = Order::sole();

    expect($order->status)->toBe(OrderStatus::MenungguRespons)
        ->and($order->code)->toMatch('/^SJK-\d{4}$/')
        ->and($order->technician_id)->toBe($this->technician->id)
        ->and($order->warranty_fee)->toBe(15000)
        ->and($order->preferred_date->toDateString())->toBe(now()->addDay()->toDateString())
        ->and($order->response_deadline->equalTo(now()->addMinutes(15)))->toBeTrue()
        ->and($order->offers->sole()->status)->toBe(OfferStatus::Menunggu)
        ->and($order->media->pluck('type')->all())->toEqualCanonicalizing([MediaType::Foto, MediaType::Video])
        ->and($order->statusLogs->sole()->to_status)->toBe(OrderStatus::MenungguRespons);

    $order->media->each(fn ($media) => Storage::disk('local')->assertExists($media->path));
});

it('lists the fields still missing and does not submit', function () {
    $this->actingAs($this->customer);

    Livewire::test('pages::requests.create', ['technician' => $this->technician])
        ->assertSee('Lengkapi dulu: PK AC, merek AC, alamat, tanggal, jam')
        ->call('submit')
        ->assertHasErrors(['ac_capacity', 'ac_brand', 'address', 'day', 'time_window']);

    expect(Order::count())->toBe(0);
});

it('asks for the brand name when Lainnya is chosen', function () {
    $this->actingAs($this->customer);

    fillRequest($this->technician)
        ->set('ac_brand', 'Lainnya')
        ->call('submit')
        ->assertHasErrors(['ac_brand_other' => 'required_if']);
});

it('only accepts services the technician offers', function () {
    $this->actingAs($this->customer);
    $other = Service::factory()->create();

    fillRequest($this->technician)
        ->set('service_id', $other->id)
        ->call('submit')
        ->assertHasErrors(['service_id' => 'in']);
});

it('rejects a time slot that has already passed today', function () {
    $this->actingAs($this->customer);
    $this->travelTo(now()->setTime(13, 0));

    fillRequest($this->technician)
        ->set('day', 0)
        ->set('time_window', 'pagi')
        ->call('submit')
        ->assertHasErrors(['time_window']);
});

it('refuses requests while the technician is not accepting orders', function () {
    $this->actingAs($this->customer);
    $this->technician->update(['is_accepting_orders' => false]);

    fillRequest($this->technician)->call('submit')->assertStatus(422);

    expect(Order::count())->toBe(0);
});

it('re-sends a saved order to another technician without a new form', function () {
    $this->actingAs($this->customer);
    $order = Order::factory()->for($this->customer, 'customer')->for($this->service)->status(OrderStatus::CariCadangan)->offered(OfferStatus::Ditolak)->create(['notes' => 'Bocor di unit dalam']);

    $replacement = TechnicianProfile::factory()->create();
    $replacement->services()->attach($this->service);

    Livewire::withQueryParams(['pesanan' => $order->code])
        ->test('pages::requests.create', ['technician' => $replacement])
        ->assertSet('notes', 'Bocor di unit dalam')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('orders.show', $order));

    expect(Order::count())->toBe(1)
        ->and($order->refresh()->technician_id)->toBe($replacement->id)
        ->and($order->status)->toBe(OrderStatus::MenungguRespons)
        ->and($order->offers()->count())->toBe(2);
});

it('does not let a customer open someone else saved order', function () {
    $this->actingAs($this->customer);
    $foreign = Order::factory()->status(OrderStatus::CariCadangan)->create();

    Livewire::withQueryParams(['pesanan' => $foreign->code])
        ->test('pages::requests.create', ['technician' => $this->technician])
        ->assertNotFound();
});
