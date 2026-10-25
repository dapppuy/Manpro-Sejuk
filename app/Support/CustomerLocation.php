<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * The service location the customer is searching from, kept in the session.
 * Signed-in customers fall back to their primary saved address.
 */
class CustomerLocation
{
    private const SESSION_KEY = 'sejuk.location';

    public function __construct(private Session $session) {}

    /**
     * @return array{address: string, label: string|null, detail: string|null, latitude: float|null, longitude: float|null}|null
     */
    public function current(?User $user = null): ?array
    {
        /** @var array{address: string, label: string|null, detail: string|null, latitude: float|null, longitude: float|null}|null $stored */
        $stored = $this->session->get(self::SESSION_KEY);

        if ($stored !== null) {
            return $stored;
        }

        $primary = $user?->isCustomer() ? $user->primaryAddress : null;

        if ($primary === null) {
            return null;
        }

        return [
            'address' => $primary->address,
            'label' => $primary->label,
            'detail' => $primary->detail,
            'latitude' => $primary->latitude,
            'longitude' => $primary->longitude,
        ];
    }

    public function set(string $address, ?float $latitude, ?float $longitude, ?string $label = null, ?string $detail = null): void
    {
        $this->session->put(self::SESSION_KEY, [
            'address' => $address,
            'label' => $label,
            'detail' => $detail,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    /**
     * Coordinates to measure distance from, defaulting to the city center.
     *
     * @return array{0: float, 1: float}
     */
    public function coordinates(?User $user = null): array
    {
        $current = $this->current($user);

        if ($current !== null && $current['latitude'] !== null && $current['longitude'] !== null) {
            return [$current['latitude'], $current['longitude']];
        }

        return [(float) config('sejuk.default_location.latitude'), (float) config('sejuk.default_location.longitude')];
    }

    /**
     * Short text for the header chip.
     */
    public function shortLabel(?User $user = null): ?string
    {
        $current = $this->current($user);

        if ($current === null) {
            return null;
        }

        return $current['label'] ?: str($current['address'])->before(',')->limit(28)->toString();
    }
}
