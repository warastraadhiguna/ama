<?php

namespace Modules\Integrity\Domain\Support;

/**
 * Great-circle distance between two points. PostGIS (already in the stack
 * for the Location module later) would be the natural home for this once
 * real geospatial queries exist, but a single-pair distance for the
 * impossible-travel check doesn't need a spatial column or index yet.
 */
class HaversineDistance
{
    private const EARTH_RADIUS_KM = 6371.0;

    public static function kilometers(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
