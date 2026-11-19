<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantMonthMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SummerStorageService
{
    public function __construct(
        private readonly BillingPolicy $policy,
        private readonly TenantBalanceService $balances,
    ) {}

    public function update(array $tenantIds, array $months, string $mode, ?string $amount, bool $editingAmount): array
    {
        return DB::transaction(function () use ($tenantIds, $months, $mode, $amount, $editingAmount) {
            $tenants = Tenant::with(['user', 'roomAssignments.bed.room'])->whereKey($tenantIds)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $updated = 0;
            $warnings = [];

            foreach ($tenantIds as $id) {
                $tenant = $tenants->get($id);
                if ($tenant?->status !== Tenant::STATUS_ACTIVE) {
                    $warnings[] = ($tenant?->name ?? 'Tenant #'.$id).' was skipped because she is no longer active.';
                    continue;
                }

                foreach ($months as $month) {
                    $label = $tenant->name.' · '.$month->format('F Y');
                    if (! $this->policy->billableMonth($tenant, $month)) {
                        $warnings[] = $label.' was skipped because the tenant was not billable in that month.';
                        continue;
                    }

                    $modes = $tenant->monthModes()->whereDate('month', $month);
                    $previous = $modes->first();
                    if ($editingAmount && $previous?->mode !== TenantMonthMode::STORAGE) {
                        $warnings[] = $label.' was skipped because it is currently Regular.';
                        continue;
                    }

                    $payments = $tenant->payments()->whereNull('utility_bill_id')
                        ->whereDate('due_date', '>=', $month)->whereDate('due_date', '<=', $month->copy()->endOfMonth())
                        ->orderBy('id')->lockForUpdate()->get();
                    $hasPayment = $payments->contains(fn (Payment $entry) => $entry->hasRecordedPayment());

                    if ($mode === TenantMonthMode::REGULAR
                        && $previous?->mode === TenantMonthMode::STORAGE
                        && $payments->contains(fn (Payment $entry) => $entry->category === Payment::STORAGE && $entry->hasRecordedPayment())) {
                        $warnings[] = $label.' was kept as Storage because its storage fee has already been paid.';
                        continue;
                    }

                    $storage = null;
                    if ($mode === TenantMonthMode::STORAGE) {
                        $storage = $previous ?? $tenant->monthModes()->make(['month' => $month]);
                        $storage->mode = $mode;
                        if ($editingAmount) {
                            $storage->amount = $amount;
                        }
                        $storage->save();
                    } else {
                        $modes->delete();
                    }

                    if ($hasPayment) {
                        $warnings[] = $label.' already has a rent or storage payment. Its recorded charges were kept.';
                    } elseif ($payments->isNotEmpty() && ($editingAmount || $storage || $previous?->mode === TenantMonthMode::STORAGE)) {
                        $charge = $this->balances->monthlyCharge($tenant, $month, $storage);
                        if ($charge && $charge->amount > 0) {
                            $payments->first()->update($charge->getAttributes());
                            $payments->slice(1)->each->delete();
                        } else {
                            $payments->each->delete();
                        }
                    }

                    $utilities = $tenant->payments()->whereHas('utilityBill', fn ($bill) => $bill
                        ->whereDate('billing_period_start', '<=', $month->copy()->endOfMonth())
                        ->whereDate('billing_period_end', '>=', $month))->get();
                    if ($utilities->contains(fn (Payment $entry) => $entry->hasRecordedPayment())) {
                        $warnings[] = $label.' already has a utility payment. Existing utility shares were kept.';
                    } elseif ($storage && config('dorm.storage_skips_utilities') && $utilities->isNotEmpty()) {
                        $warnings[] = $label.' has existing utility shares. These were kept.';
                    }

                    $updated++;
                }
            }

            return compact('updated', 'warnings');
        }, 3);
    }
}
