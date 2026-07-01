<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bed extends Model
{
    protected $fillable = ['room_id', 'bed_label', 'status'];

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
