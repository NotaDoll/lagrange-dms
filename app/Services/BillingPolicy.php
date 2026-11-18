<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Carbon;

class BillingPolicy
{
    public function __construct(private readonly SettingsService $settings) {}

    public function startDate(): Carbon
    {
        return Carbon::parse($this->settings->get('general', 'billing_start_date', config('dorm.billing_start_date')))->startOfDay();
    }

    public function tenantStart(Tenant $tenant): ?Carbon
    {
        return $tenant->hasMovedIn()
            ? $tenant->actualMoveInDate()->max($this->startDate())
            : null;
    }

    public function firstRentDueDate(Tenant $tenant): ?Carbon
    {
        $moveIn = $tenant->actualMoveInDate();

        return $moveIn?->copy()->addMonthNoOverflow()->startOfDay();
    }

    /**
     * Rent falls due every month on the tenant's move-in day (moved in on the 10th → due every 10th).
     * In shorter months a 29th–31st move-in day falls on the month's last day.
     */
    public function dueDate(Tenant $tenant, Carbon $month): ?Carbon
    {
        $moveIn = $tenant->actualMoveInDate();
        if ($moveIn === null) {
            return null;
        }

        $first = $month->copy()->startOfMonth()->startOfDay();

        return $first->day(min($moveIn->day, $first->daysInMonth));
    }

    public function assignmentOverlaps(Tenant $tenant, Carbon $from, Carbon $to): bool
    {
        $tenant->loadMissing('roomAssignments');

        return $tenant->roomAssignments->contains(fn ($assignment) =>
            $assignment->start_date->lte($to)
            && (! $assignment->end_date
                || ($assignment->end_date->gte($assignment->start_date) && $assignment->end_date->gte($from))));
    }

    public function billableMonth(Tenant $tenant, Carbon $month): bool
    {
        $start = $this->tenantStart($tenant);
        $end = $month->copy()->endOfMonth()->startOfDay();

        return $start !== null && $start->lte($end) && $this->assignmentOverlaps($tenant, $month->copy()->max($start), $end);
    }
}
