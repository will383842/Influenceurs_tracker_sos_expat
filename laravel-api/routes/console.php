<?php

use App\Jobs\CheckRemindersJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new CheckRemindersJob)->hourly();

// Daily database backup at 3:00 AM UTC
Schedule::command('backup:database')->dailyAt('03:00')->withoutOverlapping();
