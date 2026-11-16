<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs the task scheduler from an HTTP call, for hosts without a cron daemon (Vercel).
 * An external cron service calls /cron/{token} every minute; the token keeps it private.
 */
class RunScheduleController extends Controller
{
    public function __invoke(string $token): JsonResponse
    {
        $expected = (string) config('sejuk.cron_token');

        abort_if($expected === '' || ! hash_equals($expected, $token), 404);

        Artisan::call('schedule:run');

        return response()->json(['ran' => true, 'output' => trim(Artisan::output())]);
    }
}
