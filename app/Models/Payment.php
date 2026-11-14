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

    protected $fillable = [
        'tenant_id',
        'receipt_number',
        'charge_type',
        'amount',
        'due_date',
        'paid_date',
        'days_overdue',
        'status',
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
        if ($this->category === self::RENT && $this->due_date) {
            return $this->category_label . ' - ' . $this->due_date->format('F Y');
        }

        return $this->category_label;
    }

    /**
     * True when this entry has already been collected.
     * Used by PaymentLedgerService::markPaid() to avoid paying twice.
     */
    public function hasRecordedPayment(): bool
    {
        return $this->status === 'paid' && $this->paid_date !== null;
    }
}
