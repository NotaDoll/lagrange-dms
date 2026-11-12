<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    public const RENT = 'rent';
    public const STORAGE = 'storage';
    public const OPENING_BALANCE = 'opening_balance';
    public const UTILITIES = 'utilities';

    public const CATEGORY_LABELS = [
        self::RENT => 'Rent',
        self::STORAGE => 'Storage fees',
        self::OPENING_BALANCE => 'Balance forward',
        self::UTILITIES => 'Utilities',
    ];

    protected $fillable = ['utility_bill_id', 'charge_type', 'tenant_id', 'amount', 'due_date', 'paid_date', 'days_overdue', 'status'];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_date' => 'date',
    ];

    public function utilityBill()
    {
        return $this->belongsTo(UtilityBill::class);
    }

    public function getCategoryAttribute(): string
    {
        return $this->utility_bill_id ? self::UTILITIES : ($this->charge_type ?? self::RENT);
    }

    public function getDescriptionAttribute(): string
    {
        return match ($this->category) {
            self::UTILITIES => $this->utilityBill->label.' · Room '.$this->utilityBill->room->room_number,
            self::STORAGE => 'Storage fee - '.$this->due_date->format('F Y'),
            self::OPENING_BALANCE => 'Balance forward',
            default => 'Rent / room charge',
        };
    }

    public function hasRecordedPayment(): bool
    {
        return $this->status === 'paid' || $this->paid_date !== null;
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
