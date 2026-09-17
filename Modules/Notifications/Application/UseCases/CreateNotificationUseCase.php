<?php

namespace Modules\Notifications\Application\UseCases;

use App\Models\Device;
use App\Models\Notification;
use App\Models\User;
use Modules\Notifications\Domain\Contracts\PushNotifierInterface;

class CreateNotificationUseCase
{
    public function __construct(
        private readonly PushNotifierInterface $pushNotifier,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $recipient, string $type, string $title, string $body, array $data = []): Notification
    {
        $notification = Notification::create([
            'user_id' => $recipient->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        // Best-effort: a failed/unconfigured push must never fail the
        // triggering action (e.g. completing an activity) — the
        // notification is already saved and visible in the API regardless.
        $recipient->devices()
            ->whereNotNull('fcm_token')
            ->whereNull('revoked_at')
            ->each(fn (Device $device) => $this->pushNotifier->push($device, $notification));

        return $notification;
    }
}
