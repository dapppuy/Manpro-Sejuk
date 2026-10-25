<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TechnicianDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TechnicianDocumentController extends Controller
{
    /**
     * Stream a private verification document to an admin.
     */
    public function __invoke(TechnicianDocument $document): StreamedResponse
    {
        $disk = Storage::disk(TechnicianDocument::DISK);

        abort_unless($disk->exists($document->path), 404);

        return $disk->response($document->path, headers: [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
