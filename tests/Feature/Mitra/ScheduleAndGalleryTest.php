<?php

use App\Models\TechnicianPhoto;
use App\Models\TechnicianProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(now()->setTime(7, 0));
    $this->technician = TechnicianProfile::factory()->create();
    $this->actingAs($this->technician->user);
});

it('saves the weekly schedule that drives availability today', function () {
    $today = now()->dayOfWeek;

    Livewire::test('pages::mitra.schedule')
        ->call('toggle', $today, 'pagi')
        ->call('toggle', $today, 'sore')
        ->call('saveWeekly');

    expect($this->technician->availabilities()->whereNull('date')->count())->toBe(2)
        ->and($this->technician->refresh()->isAvailableToday())->toBeTrue();
});

it('marks a date as a day off and undoes it', function () {
    $this->technician->availabilities()->create(['day_of_week' => now()->dayOfWeek, 'time_window' => 'pagi', 'is_available' => true]);
    $today = now()->toDateString();

    $page = Livewire::test('pages::mitra.schedule')->call('toggleDayOff', $today);
    expect($this->technician->refresh()->isAvailableToday())->toBeFalse();

    $page->call('toggleDayOff', $today);
    expect($this->technician->refresh()->isAvailableToday())->toBeTrue();
});

it('uploads gallery photos to public storage and deletes them', function () {
    Storage::fake('public');

    Livewire::test('pages::mitra.gallery')
        ->set('uploads', [UploadedFile::fake()->create('hasil.jpg', 300, 'image/jpeg')])
        ->assertHasNoErrors();

    $photo = TechnicianPhoto::sole();
    Storage::disk('public')->assertExists($photo->path);

    Livewire::test('pages::mitra.gallery')
        ->call('updateCaption', $photo->id, 'Cuci AC 1 PK')
        ->call('deletePhoto', $photo->id);

    expect(TechnicianPhoto::count())->toBe(0);
    Storage::disk('public')->assertMissing($photo->path);
});

it('limits the gallery to 12 photos', function () {
    Storage::fake('public');
    $this->technician->photos()->createMany(array_fill(0, 12, ['path' => 'gallery/x.jpg']));

    Livewire::test('pages::mitra.gallery')
        ->set('uploads', [UploadedFile::fake()->create('lebih.jpg', 100, 'image/jpeg')])
        ->assertHasErrors(['uploads']);

    expect($this->technician->photos()->count())->toBe(12);
});

it('cannot delete another technician photo', function () {
    $foreign = TechnicianProfile::factory()->create()->photos()->create(['path' => 'gallery/y.jpg']);

    Livewire::test('pages::mitra.gallery')->call('deletePhoto', $foreign->id)->assertNotFound();
});
