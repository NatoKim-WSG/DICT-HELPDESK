<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('tickets:send-alert-emails')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('helpdesk:cleanup-runtime')
    ->dailyAt('03:10')
    ->withoutOverlapping();
