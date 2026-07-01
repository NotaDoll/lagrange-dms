<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiskFlag extends Model
{
    protected $fillable = [
        'tenant_id', 'risk_level', 'late_payments_6mo', 'consecutive_late_months',
        'avg_days_overdue', 'days_since_last_default', 'calculated_at',
    ];

    protected $casts = [
        'avg_days_overdue' => 'decimal:1',
        'calculated_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
