<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bed extends Model
{
    protected $fillable = ['room_id', 'bed_label', 'status'];

    /** Occupancy is derived from the active assignment; only availability/maintenance is stored. */
    public function getStatusAttribute($value): string
    {
        if ($this->relationLoaded('currentAssignment')) {
            return $this->getRelation('currentAssignment') ? 'occupied' : ($value ?? 'available');
        }

        return $this->currentAssignment()->exists() ? 'occupied' : ($value ?? 'available');
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function assignments()
    {
        return $this->hasMany(RoomAssignment::class);
    }

    public function currentAssignment()
    {
        return $this->hasOne(RoomAssignment::class)->where('status', 'active');
    }
}
