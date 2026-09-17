<?php

namespace Modules\Integrity\Application\UseCases;

use App\Models\Device;
use Modules\Integrity\Domain\Contracts\PlayIntegrityVerifierInterface;
use Modules\Integrity\Domain\ValueObjects\PlayIntegrityVerificationResult;

class VerifyPlayIntegrityUseCase
{
    public function __construct(
        private readonly PlayIntegrityVerifierInterface $verifier,
    ) {}

    public function handle(Device $device, string $integrityToken): PlayIntegrityVerificationResult
    {
        $result = $this->verifier->verify($device, $integrityToken);

        if ($result->configured && $result->status) {
            $device->update(['integrity_status' => $result->status]);
        }

        return $result;
    }
}
