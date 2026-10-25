<?php

namespace App\Http\Controllers;

use App\Models\OrderMedia;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderMediaController extends Controller
{
    /**
     * Stream a damage photo or video to someone allowed to see the order.
     */
    public function __invoke(OrderMedia $media): StreamedResponse
    {
        Gate::authorize('view', $media->order);

        $disk = Storage::disk(OrderMedia::DISK);

        abort_unless($disk->exists($media->path), 404);

        return $disk->response($media->path, headers: [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
