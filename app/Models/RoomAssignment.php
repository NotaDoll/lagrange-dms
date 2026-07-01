<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomAssignment extends Model
{
    protected $fillable = ['tenant_id', 'bed_id', 'start_date', 'end_date', 'status'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function bed()
    {
        return $this->belongsTo(Bed::class);
    }
}
