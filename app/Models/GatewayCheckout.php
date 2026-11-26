<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GatewayCheckout extends Model
{
    protected $fillable = ['tenant_id', 'reference', 'checkout_session_id', 'checkout_url', 'amount', 'status', 'receipt_number', 'paid_at'];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
