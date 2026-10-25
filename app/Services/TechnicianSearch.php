<?php

namespace App\Services;

use App\Enums\TechnicianBadge;
use App\Models\TechnicianProfile;
use Illuminate\Database\Eloquent\Collection;

/**
 * Finds published technicians near a point and ranks them.
 */
class TechnicianSearch
{
    public const SORT_RELEVANT = 'relevan';

    public const SORT_NEAREST = 'terdekat';

    public const SORT_RATING = 'rating';

    private const EARTH_RADIUS_KM = 6371;

    /**
     * @param  list<int>  $excludeIds
     * @return Collection<int, TechnicianProfile>
     */
    public function search(
        float $latitude,
        float $longitude,
        ?int $serviceId = null,
        int $radiusKm = 5,
        bool $availableToday = false,
        ?TechnicianBadge $minimumBadge = null,
        string $sort = self::SORT_RELEVANT,
        array $excludeIds = [],
    ): Collection {
        $latitudeDelta = $radiusKm / 111;
        $longitudeDelta = $radiusKm / (111 * max(cos(deg2rad($latitude)), 0.01));

        $technicians = TechnicianProfile::query()
            ->published()
            ->with(['user', 'prices', 'serviceAreas', 'availabilities'])
            ->whereBetween('latitude', [$latitude - $latitudeDelta, $latitude + $latitudeDelta])
            ->whereBetween('longitude', [$longitude - $longitudeDelta, $longitude + $longitudeDelta])
            ->when($serviceId, fn ($query) => $query->whereHas('services', fn ($services) => $services->whereKey($serviceId)))
            ->when($minimumBadge, fn ($query) => $query->whereIn('badge', TechnicianBadge::atLeast($minimumBadge)))
            ->when($excludeIds !== [], fn ($query) => $query->whereKeyNot($excludeIds))
            ->get();

        $technicians->each(function (TechnicianProfile $technician) use ($latitude, $longitude) {
            $technician->distanceKm = self::distanceKm($latitude, $longitude, (float) $technician->latitude, (float) $technician->longitude);
        });

        $filtered = $technicians
            ->filter(fn (TechnicianProfile $technician) => $technician->distanceKm <= $radiusKm)
            ->filter(fn (TechnicianProfile $technician) => ! $availableToday || $technician->isAvailableToday());

        $sorted = $filtered->sortBy(fn (TechnicianProfile $technician) => [
            $technician->isPromotedNow() ? 0 : 1,
            match ($sort) {
                self::SORT_NEAREST => $technician->distanceKm,
                self::SORT_RATING => -$technician->rating_avg,
                default => -$this->relevance($technician),
            },
            $technician->distanceKm,
        ]);

        return $sorted->values();
    }

    /**
     * Closer, available today, higher rating, and higher badge rank all score better.
     */
    public function relevance(TechnicianProfile $technician): float
    {
        return -($technician->distanceKm ?? 0) * 1.2
            + ($technician->isAvailableToday() ? 2 : 0)
            + $technician->rating_avg * 1.5
            + $technician->badge->rank() * 0.8;
    }

    /**
     * Great-circle distance in kilometers (Haversine).
     */
    public static function distanceKm(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
