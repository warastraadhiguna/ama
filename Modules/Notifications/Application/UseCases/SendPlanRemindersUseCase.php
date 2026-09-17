<?php

namespace Modules\Notifications\Application\UseCases;

use App\Models\ActivityPlan;
use App\Models\Notification;
use Illuminate\Support\Collection;
use Modules\Planning\Domain\Enums\PlanStatus;

/**
 * docs section 28 examples: "rencana kegiatan besok", "rencana kegiatan
 * hari ini", "rencana belum direalisasikan". Meant to run daily (see
 * routes/console.php's schedule). Each plan is only reminded once per
 * type — re-running the command (or a missed day catching up) must not
 * spam the same reminder repeatedly.
 */
class SendPlanRemindersUseCase
{
    public function __construct(
        private readonly CreateNotificationUseCase $createNotification,
    ) {}

    /**
     * @return array{tomorrow: int, today: int, overdue: int}
     */
    public function handle(): array
    {
        $today = now()->startOfDay();

        return [
            'tomorrow' => $this->remind(
                ActivityPlan::whereDate('planned_date', $today->copy()->addDay())
                    ->whereIn('status', [PlanStatus::Planned, PlanStatus::Ready])
                    ->get(),
                'PLAN_TOMORROW',
                fn (ActivityPlan $plan) => "Rencana besok: {$plan->activityType->name} di {$plan->location}.",
            ),
            'today' => $this->remind(
                ActivityPlan::whereDate('planned_date', $today)
                    ->whereIn('status', [PlanStatus::Planned, PlanStatus::Ready])
                    ->get(),
                'PLAN_TODAY',
                fn (ActivityPlan $plan) => "Rencana hari ini: {$plan->activityType->name} di {$plan->location}.",
            ),
            'overdue' => $this->remind(
                ActivityPlan::whereDate('planned_date', '<', $today)
                    ->whereIn('status', [PlanStatus::Planned, PlanStatus::Ready])
                    ->get(),
                'PLAN_OVERDUE',
                fn (ActivityPlan $plan) => "Rencana belum direalisasikan: {$plan->activityType->name} di {$plan->location}.",
            ),
        ];
    }

    /**
     * @param  Collection<int, ActivityPlan>  $plans
     */
    private function remind(Collection $plans, string $type, \Closure $body): int
    {
        $sent = 0;

        foreach ($plans->load('activityType') as $plan) {
            $alreadySent = Notification::where('user_id', $plan->creator_id)
                ->where('type', $type)
                ->whereJsonContains('data->plan_id', $plan->id)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $this->createNotification->handle(
                recipient: $plan->creator,
                type: $type,
                title: match ($type) {
                    'PLAN_TOMORROW' => 'Rencana kegiatan besok',
                    'PLAN_TODAY' => 'Rencana kegiatan hari ini',
                    default => 'Rencana belum direalisasikan',
                },
                body: $body($plan),
                data: ['plan_id' => $plan->id],
            );
            $sent++;
        }

        return $sent;
    }
}
