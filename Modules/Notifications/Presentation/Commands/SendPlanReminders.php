<?php

namespace Modules\Notifications\Presentation\Commands;

use Illuminate\Console\Command;
use Modules\Notifications\Application\UseCases\SendPlanRemindersUseCase;

class SendPlanReminders extends Command
{
    protected $signature = 'notifications:plan-reminders';

    protected $description = 'Send PLAN_TOMORROW/PLAN_TODAY/PLAN_OVERDUE notifications (docs section 28)';

    public function handle(SendPlanRemindersUseCase $useCase): int
    {
        $counts = $useCase->handle();

        $this->info(sprintf(
            'Sent %d tomorrow, %d today, %d overdue reminder(s).',
            $counts['tomorrow'],
            $counts['today'],
            $counts['overdue'],
        ));

        return self::SUCCESS;
    }
}
