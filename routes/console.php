<?php

use App\Services\SmsService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sms:sync-textbee', function (SmsService $sms) {
    try {
        $updated = $sms->syncTextBeeStatuses();
        $this->info("Updated $updated TextBee SMS record(s).");

        return 0;
    } catch (\RuntimeException $exception) {
        $this->error($exception->getMessage());

        return 1;
    }
})->purpose('Refresh recent TextBee SMS delivery statuses');

Schedule::command('sms:sync-textbee')->everyFiveMinutes()->withoutOverlapping()
    ->when(fn () => filled(config('services.textbee.key')));
