<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves files on the public disk when it is stored in the database (Vercel), where the
 * usual public/storage symlink cannot reach them. Only registered for the database driver.
 */
class PublicStorageController extends Controller
{
    public function __invoke(string $path): StreamedResponse
    {
        $disk = Storage::disk('public');

        abort_if(str_contains($path, '..') || ! $disk->fileExists($path), 404);

        return $disk->response($path, headers: [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
