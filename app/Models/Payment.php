<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    public const OPENING_BALANCE = 'opening_balance';
    public const STORAGE = 'storage';
    public const RENT = 'rent';
    public const UTILITIES = 'utilities';

    public const CATEGORY_LABELS = [
        'rent' => 'Monthly Rent',
        'utilities' => 'Utility Bills',
        'storage' => 'Storage Fee',
        'opening_balance' => 'Opening Balance',
    ];

    public const TYPE_FULL = 'full';
    public const TYPE_PARTIAL = 'partial';
    public const TYPE_ADVANCE = 'advance';

    public const PAYMENT_TYPE_LABELS = [
        self::TYPE_FULL => 'Full',
        self::TYPE_PARTIAL => 'Partial',
        self::TYPE_ADVANCE => 'Advance',
    ];

    /** How far ahead an advance payment may be spread. */
    public const MAX_ADVANCE_MONTHS = 12;

    protected $fillable = [
        'tenant_id',
        'receipt_number',
        'charge_type',
        'amount',
        'due_date',
        'paid_date',
        'days_overdue',
        'status',
        'payment_type',
        'utility_bill_id',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function utilityBill()
    {
        return $this->belongsTo(UtilityBill::class);
    }

    /**
     * Imported/regular rent rows have charge_type = null, so treat null as rent
     * and utility bills (utility_bill_id set) as utilities.
     */
    public function getCategoryAttribute(): string
    {
        if ($this->utility_bill_id) {
            return self::UTILITIES;
        }

        return $this->charge_type ?: self::RENT;
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORY_LABELS[$this->category]
            ?? ucfirst(str_replace('_', ' ', $this->category));
    }

    public function getDescriptionAttribute(): string
    {
        if ($this->category === self::UTILITIES && $this->utilityBill) {
            return $this->utilityBill->label . ' · Room ' . $this->utilityBill->room->room_number;
        }

        if (in_array($this->category, [self::RENT, self::STORAGE], true) && $this->due_date) {
            return $this->category_label . ' - ' . $this->due_date->format('F Y');
        }

        return $this->category_label;
    }

    public const STATUS_LABELS = [
        'pending' => 'Not paid',
        'overdue' => 'Overdue',
        'paid' => 'Paid',
    ];

    /**
     * Status worked out from the dates, so it never goes stale: Paid once collected,
     * Overdue once the due date has passed, otherwise Not paid ("pending").
     */
    public function getCurrentStatusAttribute(): string
    {
        if ($this->hasRecordedPayment()) {
            return 'paid';
        }

        return $this->due_date && $this->due_date->lt(Tenant::localDate()) ? 'overdue' : 'pending';
    }

    /** Days late: fixed once paid, still counting while unpaid. */
    public function getCurrentDaysOverdueAttribute(): int
    {
        if ($this->hasRecordedPayment() || ! $this->due_date) {
            return (int) $this->days_overdue;
        }

        return (int) max(0, $this->due_date->diffInDays(Tenant::localDate(), false));
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->current_status];
    }

    /** Filter by the automatic status (see getCurrentStatusAttribute). */
    public function scopeWithCurrentStatus($query, ?string $status)
    {
        $today = Tenant::localDate();

        return match ($status) {
            'paid' => $query->where('status', 'paid'),
            'overdue' => $query->where('status', '!=', 'paid')->whereDate('due_date', '<', $today),
            'pending' => $query->where('status', '!=', 'paid')->whereDate('due_date', '>=', $today),
            default => $query,
        };
    }

    /**
     * True when this entry has already been collected. Status is the source of truth:
     * older or imported rows can be "paid" without a recorded paid date.
     * Used by PaymentLedgerService::markPaid() to avoid paying twice.
     */
    public function hasRecordedPayment(): bool
    {
        return $this->status === 'paid';
    }
}
