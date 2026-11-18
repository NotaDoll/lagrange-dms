<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $table = 'sms_logs';

    protected $fillable = [
        'tenant_id', 'recipient_number', 'recipient_type', 'message', 'status', 'error_message', 'sent_at', 'textbee_batch_id',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function scopePaymentFollowUpToday(Builder $query): Builder
    {
        return $query->where('recipient_type', 'guardian')
            ->where(function (Builder $query) {
                $query->where(fn (Builder $query) => $query
                    ->whereIn('status', ['queued', 'unknown'])
                    ->whereDate('created_at', today()))
                    ->orWhere(fn (Builder $query) => $query
                        ->whereIn('status', ['sent', 'delivered'])
                        ->whereDate('sent_at', today()));
            });
        // Payment reminders are the only texts sent to guardians, and the proprietor can now edit
        // their wording, so any guardian text today counts (matching on the wording would miss edits).
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
