<?php

namespace Modules\Integrity\Infrastructure;

use App\Models\Device;
use Modules\Integrity\Domain\Contracts\PlayIntegrityVerifierInterface;
use Modules\Integrity\Domain\ValueObjects\PlayIntegrityVerificationResult;

/**
 * Placeholder bound by default (see IntegrityServiceProvider) until real
 * Google Play Integrity API credentials exist. Genuine verification needs,
 * at minimum: a Google Cloud project with the Play Integrity API enabled,
 * a service account (JSON key) authorized for it, and the Android app's
 * real package name once ama-android exists (docs "AI Agent Handover
 * Note" / section 50: OPEN QUESTION, do not fake this).
 *
 * This deliberately does NOT mark the device TRUSTED (a false sense of
 * security) or REJECTED (would incorrectly block real devices) — it
 * reports itself as unconfigured and leaves the device's integrity_status
 * untouched, so nothing downstream mistakes an absent check for a passed
 * one.
 */
class NullPlayIntegrityVerifier implements PlayIntegrityVerifierInterface
{
    public function verify(Device $device, string $integrityToken): PlayIntegrityVerificationResult
    {
        return new PlayIntegrityVerificationResult(
            configured: false,
            status: null,
            detail: 'Play Integrity verification is not configured yet (no Google Play Integrity API credentials). '
                .'This token was received but not verified.',
        );
    }
}
