<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MidtransWebhookController extends Controller
{
    /**
     * Receive Midtrans payment notifications. Unknown or unsigned requests get a 403 so Midtrans retries
     * only genuine ones; everything else is acknowledged with 200.
     */
    public function __invoke(Request $request, PaymentService $payments): JsonResponse
    {
        $handled = $payments->handleNotification($request->json()->all());

        return response()->json(['received' => $handled], $handled ? 200 : 403);
    }
}
