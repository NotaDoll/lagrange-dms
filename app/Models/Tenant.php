<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'user_id', 'emergency_contact_name', 'emergency_contact_number',
        'guardian_name', 'guardian_contact_number', 'move_in_date', 'status',
    ];

    protected $casts = [
        'move_in_date' => 'date',
        // RA 10173 compliance (Sec. 3.3): guardian numbers encrypted at rest.
        // Uses APP_KEY — back up your .env's APP_KEY, changing it makes this unreadable.
        'guardian_contact_number' => 'encrypted',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function roomAssignments()
    {
        return $this->hasMany(RoomAssignment::class);
    }

    public function currentAssignment()
    {
        return $this->hasOne(RoomAssignment::class)->where('status', 'active');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }

    public function riskFlags()
    {
        return $this->hasMany(RiskFlag::class);
    }

    public function latestRiskFlag()
    {
        return $this->hasOne(RiskFlag::class)->latestOfMany('calculated_at');
    }

    public function smsLogs()
    {
        return $this->hasMany(SmsLog::class);
    }
}
