<?php

namespace Modules\Integrity\Application\UseCases;

use App\Models\CaptureSession;
use Modules\Integrity\Domain\Enums\IntegrityStatus;

/**
 * docs section 19.6 (Layer 5): GPS and photo should land within a close
 * time window of each other. This doesn't block /complete (V1 must not
 * force rejection — docs section 23) — it only annotates the location's
 * integrity_status/anomaly_reasons for later review, the same as the
 * other signals evaluated at submission time.
 */
class EvaluateCaptureSessionWindowUseCase
{
    public function handle(CaptureSession $session): void
    {
        $location = $session->locations()->first();
        $photo = $session->photos()->first();

        if (! $location || ! $photo) {
            return;
        }

        $deltaSeconds = abs($location->captured_at_device->diffInSeconds($photo->captured_at_device));

        if ($deltaSeconds <= (int) config('integrity.capture_session_max_window_seconds')) {
            return;
        }

        $location->update([
            'anomaly_reasons' => array_values(array_unique([...($location->anomaly_reasons ?? []), 'CAPTURE_WINDOW_EXCEEDED'])),
            'integrity_status' => $location->integrity_status === IntegrityStatus::Rejected
                ? IntegrityStatus::Rejected
                : IntegrityStatus::Suspicious,
        ]);
    }
}
