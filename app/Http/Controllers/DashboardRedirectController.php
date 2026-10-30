<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardRedirectController extends Controller
{
    /**
     * Send the signed-in user to their own portal.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect($request->user()->homeRoute());
    }
}
