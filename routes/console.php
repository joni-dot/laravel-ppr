<?php

use Illuminate\Support\Facades\Schedule;

// Early morning in Finland (06:00 or 07:00), before the working day, so reminders are waiting when people start.
Schedule::command('packages:check-risks')
    ->dailyAt('04:00')
    ->timezone('UTC')
    ->withoutOverlapping()
    ->onOneServer();
