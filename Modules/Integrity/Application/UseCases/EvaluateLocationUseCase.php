<?php

namespace Modules\Integrity\Application\UseCases;

use App\Models\ActivityLocation;
use App\Models\User;
use Modules\Integrity\Domain\Enums\IntegrityStatus;
use Modules\Integrity\Domain\Support\HaversineDistance;
use Modules\Integrity\Domain\ValueObjects\LocationEvaluationResult;

/**
 * docs section 19, layers 1/3/4 combined into one status +  the specific
 * reasons behind it (layer 6). Layer 2 (Play Integrity) is device-level,
 * not per-location — see VerifyPlayIntegrityUseCase. Layer 5 (capture
 * session time window) is evaluated at /complete, once both a location and
 * a photo exist to compare — see Evidence's CompleteActivityUseCase.
 */
class EvaluateLocationUseCase
{
    /**
     * @param  array{is_mock_location: bool, latitude: float, longitude: float, accuracy: float, captured_at_device: string}  $data
     */
    public function handle(User $user, array $data): LocationEvaluationResult
    {
        $reasons = [];

        if ($data['is_mock_location']) {
            $reasons[] = 'MOCK_LOCATION_DETECTED';
        }

        if ($data['accuracy'] > (float) config('integrity.max_acceptable_accuracy_meters')) {
            $reasons[] = 'LOW_GPS_ACCURACY';
        }

        if ($this->impliesImpossibleTravel($user, $data)) {
            $reasons[] = 'IMPOSSIBLE_TRAVEL';
        }

        return new LocationEvaluationResult($this->statusFor($reasons), $reasons);
    }

    /**
     * @param  array{latitude: float, longitude: float, captured_at_device: string}  $data
     */
    private function impliesImpossibleTravel(User $user, array $data): bool
    {
        $previous = ActivityLocation::query()
            ->whereHas('activity', fn ($query) => $query->where('creator_id', $user->id))
            ->where('captured_at_device', '<', $data['captured_at_device'])
            ->orderByDesc('captured_at_device')
            ->first();

        if (! $previous) {
            return false;
        }

        $hoursElapsed = $previous->captured_at_device->diffInSeconds($data['captured_at_device']) / 3600;

        // Two locations logged in the same instant/session aren't a travel
        // claim at all — nothing to divide by, and nothing to flag.
        if ($hoursElapsed <= 0) {
            return false;
        }

        $distanceKm = HaversineDistance::kilometers(
            (float) $previous->latitude,
            (float) $previous->longitude,
            $data['latitude'],
            $data['longitude'],
        );

        $impliedSpeedKmh = $distanceKm / $hoursElapsed;

        return $impliedSpeedKmh > (float) config('integrity.max_plausible_speed_kmh');
    }

    /**
     * @param  list<string>  $reasons
     */
    private function statusFor(array $reasons): IntegrityStatus
    {
        if (in_array('MOCK_LOCATION_DETECTED', $reasons, true) && config('integrity.mock_location_policy') === 'BLOCK') {
            return IntegrityStatus::Rejected;
        }

        if ($reasons !== []) {
            return IntegrityStatus::Suspicious;
        }

        return IntegrityStatus::Trusted;
    }
}
