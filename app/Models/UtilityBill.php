<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UtilityBill extends Model
{
    protected $fillable = [
        'room_id', 'bill_type', 'billing_period_start', 'billing_period_end',
        'amount', 'due_date', 'paid_date', 'days_overdue', 'status', 'notes',
    ];

    protected $casts = [
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'due_date' => 'date',
        'paid_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}