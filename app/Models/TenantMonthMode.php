<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantMonthMode extends Model
{
    public const REGULAR = 'regular';
    public const STORAGE = 'storage';

    protected $fillable = ['tenant_id', 'month', 'mode', 'amount'];

    protected $casts = [
        'month' => 'date',
        'amount' => 'decimal:2',
    ];

    public function storageFee(): float
    {
        return (float) ($this->amount ?? config('dorm.storage_fee'));
    }
}
