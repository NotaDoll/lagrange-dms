<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = ['room_number', 'floor', 'capacity', 'monthly_rate', 'status'];

    protected $casts = [
        'monthly_rate' => 'decimal:2',
    ];

    public function beds()
    {
        return $this->hasMany(Bed::class);
    }
    
    public function utilityBills()
{
    return $this->hasMany(UtilityBill::class);
}
}
