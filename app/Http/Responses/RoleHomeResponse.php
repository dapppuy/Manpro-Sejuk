<?php

namespace App\Http\Responses;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends each user to their own portal after signing in or registering.
 */
class RoleHomeResponse implements LoginResponse, RegisterResponse, TwoFactorLoginResponse
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        /** @var User $user */
        $user = $request->user();
        $intended = $request->session()->pull('url.intended');

        return redirect(is_string($intended) && $this->belongsToPortal($user, $intended) ? $intended : $user->homeRoute());
    }

    /**
     * Only follow an intended URL inside the signed-in user's own portal, so a page left over
     * from another account (e.g. a customer order) does not end in a 403.
     */
    private function belongsToPortal(User $user, string $url): bool
    {
        $path = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $isMitra = Str::startsWith($path, '/mitra');
        $isAdmin = Str::startsWith($path, '/admin');

        return match ($user->role) {
            UserRole::Technician => $isMitra,
            UserRole::Admin => $isAdmin,
            UserRole::Customer => ! $isMitra && ! $isAdmin,
        };
    }
}
