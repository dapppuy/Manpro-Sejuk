<?php

use App\Enums\FundStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\PublicStorageController;
use App\Models\Order;
use App\Models\TechnicianProfile;
use App\Models\WorkProof;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Points a disk at the "database" driver, the way Vercel is configured.
 */
function useDatabaseDisk(string $name): void
{
    config(["filesystems.disks.{$name}" => ['driver' => 'database', 'namespace' => $name, 'throw' => false]]);
    Storage::forgetDisk($name);
}

it('stores, lists, moves and deletes files in the database', function () {
    useDatabaseDisk('local');
    useDatabaseDisk('public');
    $disk = Storage::disk('local');

    $disk->put('order-media/1/a.txt', 'halo');
    $disk->put('order-media/1/nested/b.txt', 'dunia');

    expect($disk->get('order-media/1/a.txt'))->toBe('halo')
        ->and($disk->size('order-media/1/a.txt'))->toBe(4)
        ->and($disk->exists('order-media/1'))->toBeTrue()
        ->and($disk->files('order-media/1'))->toBe(['order-media/1/a.txt'])
        ->and($disk->allFiles('order-media'))->toBe(['order-media/1/a.txt', 'order-media/1/nested/b.txt'])
        ->and($disk->directories('order-media/1'))->toBe(['order-media/1/nested'])
        ->and(Storage::disk('public')->exists('order-media/1/a.txt'))->toBeFalse();

    $disk->move('order-media/1/a.txt', 'order-media/2/a.txt');
    expect($disk->exists('order-media/1/a.txt'))->toBeFalse()
        ->and($disk->get('order-media/2/a.txt'))->toBe('halo');

    $disk->deleteDirectory('order-media/1');
    expect($disk->allFiles())->toBe(['order-media/2/a.txt']);
});

it('keeps binary photos intact and reports their mime type', function () {
    useDatabaseDisk('local');
    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

    Storage::disk('local')->put('foto.png', $bytes);

    expect(Storage::disk('local')->get('foto.png'))->toBe($bytes)
        ->and(Storage::disk('local')->mimeType('foto.png'))->toBe('image/png');
});

it('accepts both before and after photos when uploads live in the database', function () {
    useDatabaseDisk('local');
    useDatabaseDisk('tmp-for-tests');

    $technician = TechnicianProfile::factory()->create(['jobs_count' => 10]);
    $order = Order::factory()->for($technician, 'technician')->withEstimate()->create([
        'status' => OrderStatus::Dikerjakan,
        'final_total' => 550000,
        'fund_status' => FundStatus::Ditahan,
    ]);

    $this->actingAs($technician->user);

    Livewire::test('pages::mitra.orders.proof', ['order' => $order])
        ->set('before', UploadedFile::fake()->create('sebelum.jpg', 200, 'image/jpeg'))
        ->set('after', UploadedFile::fake()->create('sesudah.jpg', 200, 'image/jpeg'))
        ->assertHasNoErrors(['before', 'after'])
        ->set('note', 'Cuci indoor dan outdoor.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('mitra.orders.show', $order));

    $proof = WorkProof::sole();

    expect(DB::table('stored_files')->where('disk', 'local')->whereIn('path', [$proof->before_path, $proof->after_path])->count())->toBe(2);

    $this->get(route('orders.proofs.photo', [$proof, 'sesudah']))->assertOk();
});

it('serves public disk files stored in the database', function () {
    useDatabaseDisk('public');
    Storage::disk('public')->put('avatars/foto.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

    $response = (new PublicStorageController)('avatars/foto.png');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toBe('image/png');

    expect(fn () => (new PublicStorageController)('../.env'))
        ->toThrow(NotFoundHttpException::class);
});
