<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RoomAssignment extends Model
{
    protected $fillable = ['tenant_id', 'bed_id', 'start_date', 'end_date', 'status'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function scopeBillable(Builder $query): Builder
    {
        return $query->whereHas('tenant', fn ($tenant) => $tenant->movedIn())
            ->where(fn ($assignments) => $assignments->whereNull('end_date')->orWhere(function ($ended) {
                $ended->whereColumn('end_date', '>=', 'start_date')
                    ->whereHas('tenant', fn ($tenant) => $tenant
                        ->whereColumn('move_in_date', '<=', 'room_assignments.end_date'));
            }));
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function bed()
    {
        return $this->belongsTo(Bed::class);
    }
}
