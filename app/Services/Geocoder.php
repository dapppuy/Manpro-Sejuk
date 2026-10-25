<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Address lookups through OpenStreetMap Nominatim, biased to the service city.
 */
class Geocoder
{
    /**
     * Coordinates for a typed address, or null when nothing matches.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public function search(string $address): ?array
    {
        $query = Str::contains(Str::lower($address), Str::lower((string) config('sejuk.city')))
            ? $address
            : $address.', '.config('sejuk.city');

        return Cache::remember('geocode:search:'.md5(Str::lower($query)), now()->addDays(30), function () use ($query) {
            try {
                $response = $this->client()->get('/search', [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'countrycodes' => 'id',
                ]);
            } catch (ConnectionException) {
                return null;
            }

            $first = $response->successful() ? $response->json('0') : null;

            return is_array($first) ? ['latitude' => (float) $first['lat'], 'longitude' => (float) $first['lon']] : null;
        });
    }

    /**
     * A readable street address for coordinates, or null when the lookup fails.
     */
    public function reverse(float $latitude, float $longitude): ?string
    {
        $key = 'geocode:reverse:'.round($latitude, 4).','.round($longitude, 4);

        return Cache::remember($key, now()->addDays(30), function () use ($latitude, $longitude) {
            try {
                $response = $this->client()->get('/reverse', [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'format' => 'jsonv2',
                    'zoom' => 17,
                ]);
            } catch (ConnectionException) {
                return null;
            }

            if (! $response->successful()) {
                return null;
            }

            $parts = collect([
                $response->json('address.road'),
                $response->json('address.suburb') ?? $response->json('address.village'),
                $response->json('address.city') ?? $response->json('address.town') ?? $response->json('address.county'),
            ])->filter()->unique();

            return $parts->isNotEmpty() ? $parts->implode(', ') : $response->json('display_name');
        });
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.nominatim.url'))
            ->withUserAgent((string) config('services.nominatim.user_agent'))
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(6);
    }
}
