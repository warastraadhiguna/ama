<?php

namespace Modules\Evidence\Application\UseCases;

use App\Models\Activity;
use App\Models\ActivityLocation;
use App\Models\CaptureSession;
use App\Models\User;
use Modules\Evidence\Application\Support\ResolveCurrentDevice;
use Modules\Evidence\Domain\Exceptions\LocationRejectedException;
use Modules\Evidence\Domain\Rules\EnsureActivityIsEditable;
use Modules\Integrity\Application\UseCases\EvaluateLocationUseCase;
use Modules\Integrity\Domain\Enums\IntegrityStatus;

class SubmitLocationUseCase
{
    public function __construct(
        private readonly EnsureActivityIsEditable $ensureActivityIsEditable,
        private readonly ResolveCurrentDevice $resolveCurrentDevice,
        private readonly EvaluateLocationUseCase $evaluateLocation,
    ) {}

    /**
     * @param  array{capture_session_uuid: string, started_at: string, latitude: float, longitude: float, accuracy: float, altitude: ?float, speed: ?float, bearing: ?float, provider: ?string, is_mock_location: bool, captured_at_device: string}  $data
     */
    public function handle(Activity $activity, User $user, ?string $deviceTokenName, array $data): ActivityLocation
    {
        ($this->ensureActivityIsEditable)($activity);

        $evaluation = $this->evaluateLocation->handle($user, $data);

        if ($evaluation->status === IntegrityStatus::Rejected) {
            throw new LocationRejectedException($evaluation->reasons);
        }

        $session = CaptureSession::firstOrCreate(
            ['activity_id' => $activity->id, 'client_uuid' => $data['capture_session_uuid']],
            [
                'started_at' => $data['started_at'],
                'device_id' => $this->resolveCurrentDevice->handle($user, $deviceTokenName)?->id,
            ],
        );

        return ActivityLocation::create([
            'activity_id' => $activity->id,
            'capture_session_id' => $session->id,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'accuracy' => $data['accuracy'],
            'altitude' => $data['altitude'] ?? null,
            'speed' => $data['speed'] ?? null,
            'bearing' => $data['bearing'] ?? null,
            'provider' => $data['provider'] ?? null,
            'is_mock_location' => $data['is_mock_location'],
            'captured_at_device' => $data['captured_at_device'],
            'received_at_server' => now(),
            'integrity_status' => $evaluation->status,
            'anomaly_reasons' => $evaluation->reasons,
        ]);
    }
}
