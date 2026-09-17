<?php

namespace Modules\Evidence\Application\UseCases;

use App\Models\Activity;
use App\Models\ActivityPhoto;
use App\Models\CaptureSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Evidence\Application\Support\ResolveCurrentDevice;
use Modules\Evidence\Domain\Rules\EnsureActivityIsEditable;

class UploadPhotoUseCase
{
    public function __construct(
        private readonly EnsureActivityIsEditable $ensureActivityIsEditable,
        private readonly ResolveCurrentDevice $resolveCurrentDevice,
    ) {}

    /**
     * @param  array{capture_session_uuid: string, started_at: string, latitude: float, longitude: float, accuracy: float, captured_at_device: string}  $data
     */
    public function handle(Activity $activity, User $user, ?string $deviceTokenName, UploadedFile $file, array $data): ActivityPhoto
    {
        ($this->ensureActivityIsEditable)($activity);

        $device = $this->resolveCurrentDevice->handle($user, $deviceTokenName);

        $session = CaptureSession::firstOrCreate(
            ['activity_id' => $activity->id, 'client_uuid' => $data['capture_session_uuid']],
            ['started_at' => $data['started_at'], 'device_id' => $device?->id],
        );

        $contents = $file->get();
        $hash = hash('sha256', $contents);
        $path = sprintf('activities/%d/photos/%s.%s', $activity->id, (string) Str::uuid(), $file->extension() ?: 'jpg');

        Storage::put($path, $contents);

        return ActivityPhoto::create([
            'activity_id' => $activity->id,
            'capture_session_id' => $session->id,
            'device_id' => $device?->id,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'accuracy' => $data['accuracy'],
            'captured_at_device' => $data['captured_at_device'],
            'received_at_server' => now(),
            'source' => 'CAMERA',
            'sha256_hash' => $hash,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'storage_path' => $path,
            'integrity_status' => 'PENDING',
        ]);
    }
}
