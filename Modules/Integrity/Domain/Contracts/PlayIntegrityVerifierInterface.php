<?php

namespace Modules\Integrity\Domain\Contracts;

use App\Models\Device;
use Modules\Integrity\Domain\ValueObjects\PlayIntegrityVerificationResult;

/**
 * docs section 19.3 (Layer 2): the mobile app requests a Play Integrity
 * token from Google and the server verifies it — never the APK alone
 * (docs section 43). A real implementation calls Google's Play Integrity
 * API (decoding/verifying the token against a Google Cloud service
 * account + the Android app's package name). See
 * Infrastructure/NullPlayIntegrityVerifier for why that isn't wired in yet.
 */
interface PlayIntegrityVerifierInterface
{
    public function verify(Device $device, string $integrityToken): PlayIntegrityVerificationResult;
}
