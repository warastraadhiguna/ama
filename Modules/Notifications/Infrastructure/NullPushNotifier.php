<?php

namespace Modules\Notifications\Infrastructure;

use App\Models\Device;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Modules\Notifications\Domain\Contracts\PushNotifierInterface;

/**
 * Placeholder bound by default (see NotificationsServiceProvider) until a
 * real Firebase project exists. Every notification is still saved to the
 * database (docs section 28: "Notifikasi juga disimpan dalam database agar
 * notification center tetap memiliki history") and the API/notification
 * center work regardless — only the push itself doesn't happen. Needs, at
 * minimum, a Firebase project with Cloud Messaging enabled and a service
 * account (JSON key) once ama-android exists to register real fcm_tokens
 * against. Do not silently swap this for a fake "always succeeds"
 * implementation — log-and-report-false is the honest behavior.
 */
class NullPushNotifier implements PushNotifierInterface
{
    public function push(Device $device, Notification $notification): bool
    {
        Log::info('[NullPushNotifier] Would push notification (FCM not configured)', [
            'device_id' => $device->id,
            'notification_id' => $notification->id,
            'type' => $notification->type,
        ]);

        return false;
    }
}
