<?php

namespace Modules\Notifications\Application\UseCases;

use App\Models\User;
use Modules\Audit\Application\AuditLogger;

/**
 * docs section 28: "pengumuman admin". Fans one announcement out as a normal
 * notification per recipient (so it lands in each user's notification
 * center and, once FCM exists, is pushed like any other). Only active users
 * receive it; optionally narrowed to one role and/or one work location.
 */
class SendAnnouncementUseCase
{
    public function __construct(
        private readonly CreateNotificationUseCase $createNotification,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return int number of users notified
     */
    public function handle(User $sender, string $title, string $body, ?string $role, ?int $workLocationId): int
    {
        $query = User::query()->where('is_active', true);

        if ($role !== null) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        if ($workLocationId !== null) {
            $query->where('work_location_id', $workLocationId);
        }

        $count = 0;
        $query->each(function (User $recipient) use ($title, $body, &$count): void {
            $this->createNotification->handle($recipient, 'ANNOUNCEMENT', $title, $body);
            $count++;
        });

        $this->auditLogger->log($sender, 'announced', User::class, null, newValues: [
            'title' => $title,
            'role' => $role,
            'work_location_id' => $workLocationId,
            'recipients' => $count,
        ]);

        return $count;
    }
}
