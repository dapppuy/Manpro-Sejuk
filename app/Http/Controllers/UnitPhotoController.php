<?php

namespace App\Http\Controllers;

use App\Models\CustomerUnit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UnitPhotoController extends Controller
{
    /**
     * Stream a private AC unit photo to its owner or an admin.
     *
     * $photo is "stiker" for the spec sticker, or "{logId}-sebelum" / "{logId}-sesudah" for a service visit.
     */
    public function __invoke(Request $request, CustomerUnit $unit, string $photo): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($unit->user_id === $user->id || $user->isAdmin(), 403);

        if ($photo === 'stiker') {
            $path = $unit->sticker_photo_path;
        } elseif (preg_match('/^(\d+)-(sebelum|sesudah)$/', $photo, $matches) === 1) {
            $log = $unit->serviceLogs()->findOrFail((int) $matches[1]);
            $path = $matches[2] === 'sebelum' ? $log->before_photo_path : $log->after_photo_path;
        } else {
            abort(404);
        }

        $disk = Storage::disk(CustomerUnit::PHOTO_DISK);

        abort_unless($path !== null && $disk->exists($path), 404);

        return $disk->response($path, headers: [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
