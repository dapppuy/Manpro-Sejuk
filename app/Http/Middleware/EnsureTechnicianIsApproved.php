<?php

namespace App\Http\Middleware;

use App\Enums\VerificationStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTechnicianIsApproved
{
    /**
     * Send technicians who are not verified yet to the verification status page.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $profile = $request->user()?->technicianProfile;

        if ($profile === null || $profile->verification_status !== VerificationStatus::Approved) {
            return redirect()->route('mitra.verification');
        }

        return $next($request);
    }
}
