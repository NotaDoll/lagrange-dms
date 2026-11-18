<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_LABELS = [
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_RESOLVED => 'Resolved',
    ];

    protected $fillable = ['tenant_id', 'category', 'description', 'status'];

    protected $casts = ['read_at' => 'datetime'];

    /** Complaints the proprietor still has to act on (anything not resolved). */
    public function scopeOpen($query)
    {
        return $query->where('status', '!=', self::STATUS_RESOLVED);
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
