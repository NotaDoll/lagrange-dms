<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UtilityBill extends Model
{
    protected $fillable = [
        'room_id', 'bill_type', 'billing_period_start', 'billing_period_end',
        'other_description', 'amount', 'due_date', 'paid_date', 'days_overdue', 'status', 'notes',
    ];

    protected $casts = [
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'due_date' => 'date',
        'paid_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getLabelAttribute(): string
    {
        return $this->bill_type === 'other' ? ($this->other_description ?: 'Other') : ucfirst($this->bill_type);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
