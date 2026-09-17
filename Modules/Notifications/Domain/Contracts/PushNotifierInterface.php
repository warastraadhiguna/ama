<?php

namespace Modules\Notifications\Domain\Contracts;

use App\Models\Device;
use App\Models\Notification;

/**
 * docs section 5.3/28: Firebase Cloud Messaging. A real implementation
 * sends via the FCM HTTP v1 API using a Firebase service account. See
 * Infrastructure/NullPushNotifier for why that isn't wired in yet.
 */
interface PushNotifierInterface
{
    public function push(Device $device, Notification $notification): bool;
}
