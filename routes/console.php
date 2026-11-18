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

Artisan::command('payments:align-due-dates {--apply : Save the changes (without this it only shows what would change)}', function (\App\Services\DueDateAlignment $alignment) {
    $changes = $alignment->plan();

    if ($changes === []) {
        $this->info('All rent rows are already due on the tenant\'s move-in day.');

        return 0;
    }

    $this->table(['ID', 'Tenant', 'Due date', 'New due date', 'Status', 'Days late', 'New days late'], array_map(
        fn ($c) => [$c['id'], $c['tenant'], $c['from'], $c['to'], $c['status'], $c['days_from'], $c['days_to']],
        array_slice($changes, 0, 20)
    ));
    if (count($changes) > 20) {
        $this->line('… and '.(count($changes) - 20).' more.');
    }

    if (! $this->option('apply')) {
        $this->warn(count($changes).' row(s) would change. Run again with --apply to save.');

        return 0;
    }

    $this->info($alignment->apply($changes).' row(s) moved to the tenant\'s move-in day.');

    return 0;
})->purpose('Move rent due dates to each tenant\'s move-in day (dry run unless --apply)');
