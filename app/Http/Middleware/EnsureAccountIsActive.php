<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out an account the admin has suspended, on its next request.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isSuspended()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Akunmu sedang ditangguhkan oleh tim Sejuk'.($user->suspension_reason ? ': '.$user->suspension_reason : '.').' Hubungi tim Sejuk jika ini keliru.');
        }

        return $next($request);
    }
}
