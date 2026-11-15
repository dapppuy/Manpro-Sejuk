<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectNonCustomers
{
    /**
     * Redirect authenticated technicians and admins to their respective portals
     * when they try to access customer-facing pages.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->isCustomer()) {
            return redirect($user->homeRoute());
        }

        return $next($request);
    }
}
