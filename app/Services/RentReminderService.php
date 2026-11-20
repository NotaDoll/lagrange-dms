<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * In-app reminders for the tenant's next rent (or storage fee): one when it is 1–3 days away
 * ("3 days before"; a later check still catches it if the scheduler was off that day), and one on
 * the due day. Each is sent once per due date. Nothing is sent once the charge is paid.
 */
class RentReminderService
{
    public const TYPE = 'rent_reminder';

    public function __construct(
        private readonly TenantBalanceService $balances,
        private readonly NotificationService $notifications,
    ) {}

    public function remind(Tenant $tenant): ?Notification
    {
        if ($tenant->status !== Tenant::STATUS_ACTIVE || ! $tenant->user) {
            return null;
        }

        $next = $this->balances->nextDue($tenant);
        if ($next === null || $next['overdue']) {
            return null;
        }

        $today = Carbon::parse(Tenant::localDate());
        $days = (int) $today->diffInDays($next['date']);
        if ($days > 3) {
            return null;
        }

        $stage = $days === 0 ? 'due-today' : 'upcoming';
        $charge = strtolower($next['label']);
        $amount = '₱'.number_format($next['amount'], 2);
        $date = $next['date']->format('M j, Y');
        $message = $days === 0
            ? "Your {$charge} of {$amount} is due today ({$date})."
            : "Reminder: your {$charge} of {$amount} is due on {$date} (in {$days} ".($days === 1 ? 'day' : 'days').').';

        // Anything else still unpaid (an earlier month or a utility share) is added so the tenant sees the full amount owed.
        $outstanding = $this->balances->outstandingSummary($tenant)['outstanding_balance'];
        $other = round($outstanding - ($days === 0 ? $next['amount'] : 0), 2);
        if ($other > 0) {
            $message .= ' You also have ₱'.number_format($other, 2).' in other unpaid charges, so you owe ₱'
                .number_format($other + $next['amount'], 2).' in total.';
        }

        return $this->notifications->notifyOnce($tenant->user, self::TYPE,
            'rent:'.$next['date']->toDateString().':'.$stage, $message);
    }
}
