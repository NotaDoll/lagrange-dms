<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['utility_bill_id', 'tenant_id', 'amount', 'due_date', 'paid_date', 'days_overdue', 'status'];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_date' => 'date',
    ];

    public function utilityBill()
    {
        return $this->belongsTo(UtilityBill::class);
    }

    public function getDescriptionAttribute(): string
    {
        return $this->utility_bill_id
            ? $this->utilityBill->label.' · Room '.$this->utilityBill->room->room_number
            : 'Rent / room charge';
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
