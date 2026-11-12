<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiSummary extends Model
{
    protected $fillable = [
        'generated_by',
        'filter_params',
        'prompt_used',
        'summary_result',
        'response_time_seconds',
    ];

    protected $casts = [
        'filter_params' => 'array',
        'response_time_seconds' => 'float',
    ];

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
