<?php

namespace Modules\Notifications\Application\UseCases;

use App\Models\Activity;
use App\Models\User;
use Modules\Integrity\Domain\Enums\IntegrityStatus;
use Spatie\Permission\Models\Permission;

/**
 * docs section 28 examples: "upload berhasil" and "aktivitas membutuhkan
 * review". Fired once an activity finishes /complete (Evidence module),
 * after its transaction has committed — a rolled-back completion should
 * never have notified anyone.
 */
class NotifyActivityCompletedUseCase
{
    public function __construct(
        private readonly CreateNotificationUseCase $createNotification,
    ) {}

    public function handle(Activity $activity): void
    {
        $this->createNotification->handle(
            recipient: $activity->creator,
            type: 'ACTIVITY_COMPLETED',
            title: 'Aktivitas berhasil diunggah',
            body: sprintf('Aktivitas "%s" di %s berhasil diunggah.', $activity->activityType->name, $activity->location),
            data: ['activity_id' => $activity->id],
        );

        if (! $this->hasFlaggedLocation($activity)) {
            return;
        }

        // Permission::where(...)->exists() rather than User::permission()
        // directly: spatie throws PermissionDoesNotExist instead of
        // returning an empty set when the permission isn't seeded yet in a
        // given environment (the same footgun User::role() has).
        if (! Permission::where('name', 'activities.verify')->exists()) {
            return;
        }

        foreach (User::permission('activities.verify')->get() as $reviewer) {
            $this->createNotification->handle(
                recipient: $reviewer,
                type: 'ACTIVITY_NEEDS_REVIEW',
                title: 'Aktivitas perlu ditinjau',
                body: sprintf('Aktivitas #%d oleh %s memiliki indikasi lokasi mencurigakan.', $activity->id, $activity->creator->name),
                data: ['activity_id' => $activity->id],
            );
        }
    }

    private function hasFlaggedLocation(Activity $activity): bool
    {
        return $activity->captureSessions
            ->flatMap(fn ($session) => $session->locations)
            ->contains(fn ($location) => $location->integrity_status !== IntegrityStatus::Trusted);
    }
}
