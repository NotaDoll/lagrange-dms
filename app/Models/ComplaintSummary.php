<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplaintSummary extends Model
{
    protected $fillable = [
        'user_id',
        'complaint_ids',
        'complaint_count',
        'categories_included',
        'period_start',
        'period_end',
        'summary',
    ];

    protected $casts = [
        'complaint_ids' => 'array',
        'complaint_count' => 'integer',
        'categories_included' => 'array',
        'period_start' => 'date',
        'period_end' => 'date',
    ];
}
