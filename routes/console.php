<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// docs section 28: daily plan reminders (tomorrow/today/overdue).
// Requires the Laravel scheduler to actually be running — in production,
// a cron entry `* * * * * php artisan schedule:run` (docs section 5.1
// lists "Scheduler: Laravel Scheduler"); nothing extra needed in this
// Docker Compose dev setup since it's not meant to model a full deploy.
Schedule::command('notifications:plan-reminders')->dailyAt('06:00');
