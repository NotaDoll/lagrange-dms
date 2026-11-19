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

Artisan::command('risk:backtest
    {--months=6 : How many past month-ends to test}
    {--window=2 : Months after each cutoff to check what the tenant actually did}
    {--grace= : Days late that still count as paying on time (default: the grace period in Settings)}
    {--flag=high : Count "high" only, or "medium" (medium or high), as flagged}
    {--csv= : Also save every tenant/month row to this CSV file (for Chapter 4)}', function (\App\Services\RiskBacktest $backtest, \App\Services\PaymentRiskService $risk) {
    $flag = $this->option('flag') === 'medium' ? 'medium' : 'high';
    $grace = $this->option('grace') === null ? (int) $risk->thresholds()['grace_days'] : (int) $this->option('grace');
    $result = $backtest->run((int) $this->option('months'), (int) $this->option('window'), $grace, $flag);

    if ($result['observations'] === []) {
        $this->warn('No tenant had charges due in any checked window yet. Import payment history first, or use fewer --months.');

        return 0;
    }

    $pct = fn ($value) => $value === null ? '—' : $value.'%';
    $this->info(sprintf('Risk flag backtest: flagged = %s, outcome window = %d months, late = more than %d days.',
        $flag === 'medium' ? 'Medium or High' : 'High', (int) $this->option('window'), $grace));
    $this->table(
        ['Cutoff', 'Tenants', 'TP', 'FP', 'FN', 'TN', 'Accuracy', 'False positive rate', 'Flagged but on time'],
        array_map(fn ($c) => [$c['cutoff'], $c['tenants_checked'], $c['tp'], $c['fp'], $c['fn'], $c['tn'], $pct($c['accuracy']), $pct($c['false_positive_rate']), $pct($c['flagged_but_paid_on_time'])], $result['cutoffs'])
    );

    $t = $result['totals'];
    $this->line(sprintf('All cutoffs: %d checks · accuracy %s (target 80%% or more) · false positive rate %s (target 20%% or less)',
        $t['tenants_checked'], $pct($t['accuracy']), $pct($t['false_positive_rate'])));
    $this->line(sprintf('Flagged but paid on time %s · precision %s · recall %s', $pct($t['flagged_but_paid_on_time']), $pct($t['precision']), $pct($t['recall'])));

    if ($path = $this->option('csv')) {
        $handle = fopen($path, 'w');
        fputcsv($handle, ['cutoff', 'tenant_id', 'tenant', 'risk_level', 'flagged', 'worst_days_late', 'defaulted']);
        foreach ($result['observations'] as $row) {
            fputcsv($handle, [$row['cutoff'], $row['tenant_id'], $row['tenant'], $row['risk_level'], $row['flagged'] ? 1 : 0, $row['worst_days_late'], $row['defaulted'] ? 1 : 0]);
        }
        fclose($handle);
        $this->info('Saved '.count($result['observations']).' rows to '.$path);
    }

    return 0;
})->purpose('Check payment risk flags against what tenants actually paid (accuracy, false positive rate)');

Artisan::command('admin:create', function () {
    // Asks for everything interactively so the password never appears in the shell history.
    $first = trim((string) $this->ask('First name'));
    $last = trim((string) $this->ask('Last name'));
    $email = \Illuminate\Support\Str::lower(trim((string) $this->ask('Email (used to log in)')));
    $password = (string) $this->secret('Password (at least 8 characters)');
    $confirm = (string) $this->secret('Type the password again');

    $validator = \Illuminate\Support\Facades\Validator::make(
        ['first_name' => $first, 'last_name' => $last, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirm],
        [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ],
    );
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    \App\Models\User::create([
        'name' => "$first $last", 'first_name' => $first, 'last_name' => $last, 'email' => $email,
        'password' => \Illuminate\Support\Facades\Hash::make($password), 'role' => 'proprietor',
    ]);
    $this->info("Admin account created for $first $last. Log in with $email.");

    return 0;
})->purpose('Create another admin (proprietor) login');
