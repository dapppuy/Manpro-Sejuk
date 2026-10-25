<?php

namespace App\Http\Controllers;

use App\Models\WorkProof;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkProofPhotoController extends Controller
{
    /**
     * Stream a before or after photo to someone allowed to see the order.
     */
    public function __invoke(WorkProof $proof, string $side): StreamedResponse
    {
        Gate::authorize('view', $proof->order);

        $path = match ($side) {
            'sebelum' => $proof->before_path,
            'sesudah' => $proof->after_path,
            default => abort(404),
        };

        $disk = Storage::disk(WorkProof::DISK);

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, headers: [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
