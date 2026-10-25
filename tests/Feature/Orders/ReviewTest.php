<?php

use App\Actions\Reviews\SubmitReview;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Models\Review;
use App\Models\Service;
use App\Models\TechnicianProfile;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->technician = TechnicianProfile::factory()->create(['rating_avg' => 4.0, 'reviews_count' => 1]);
    $this->order = Order::factory()
        ->for($this->technician, 'technician')
        ->for(Service::factory()->create(['name' => 'Cuci AC']))
        ->withEstimate()
        ->create(['status' => OrderStatus::Selesai, 'final_total' => 100000]);

    $this->actingAs($this->order->customer);
});

it('records a rating and story, then thanks the customer', function () {
    Livewire::test('pages::reviews.create', ['order' => $this->order])
        ->call('setRating', 5)
        ->set('comment', 'Datang tepat waktu dan rapi.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSee('Terima kasih atas ulasanmu');

    $review = Review::sole();

    expect($review->rating)->toBe(5)
        ->and($review->comment)->toBe('Datang tepat waktu dan rapi.')
        ->and($review->order_id)->toBe($this->order->id)
        ->and($review->technician_id)->toBe($this->technician->id)
        ->and($this->technician->refresh()->rating_avg)->toBe(4.5)
        ->and($this->technician->reviews_count)->toBe(2);
});

it('requires a star rating', function () {
    Livewire::test('pages::reviews.create', ['order' => $this->order])
        ->set('comment', 'Bagus')
        ->call('submit')
        ->assertHasErrors(['rating' => 'between']);

    expect(Review::count())->toBe(0);
});

it('accepts a rating without a story', function () {
    Livewire::test('pages::reviews.create', ['order' => $this->order])
        ->call('setRating', 4)
        ->call('submit')
        ->assertHasNoErrors();

    expect(Review::sole()->comment)->toBeNull();
});

it('allows only one review per order', function () {
    app(SubmitReview::class)->handle($this->order, 5, null);

    Livewire::test('pages::reviews.create', ['order' => $this->order])
        ->assertSee('Terima kasih atas ulasanmu');

    app(SubmitReview::class)->handle($this->order->refresh(), 1, 'Kedua kali');
})->throws(InvalidOrderTransition::class);

it('sends the customer back to the order before it is finished', function () {
    $this->order->update(['status' => OrderStatus::MenungguKonfirmasi]);

    $this->get(route('reviews.create', $this->order))->assertRedirect(route('orders.show', $this->order));
});

it('keeps other customers from reviewing the order', function () {
    $this->actingAs(User::factory()->customer()->create())
        ->get(route('reviews.create', $this->order))
        ->assertForbidden();
});

it('shows the review on the technician profile with the service', function () {
    app(SubmitReview::class)->handle($this->order, 5, 'AC dingin lagi, mantap.');

    $this->get(route('technicians.show', $this->technician))
        ->assertSee('AC dingin lagi, mantap.')
        ->assertSee('Cuci AC ·', false);
});

it('replaces the review button with the given rating on the finished order', function () {
    $this->get(route('orders.show', $this->order))->assertSee('Beri ulasan');

    app(SubmitReview::class)->handle($this->order, 4, 'Cukup baik.');

    $this->get(route('orders.show', $this->order))
        ->assertSee('Ulasanmu')
        ->assertSee('Cukup baik.')
        ->assertDontSee('Beri ulasan');
});
