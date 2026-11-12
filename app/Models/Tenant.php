<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory;

    public const LOCAL_TIMEZONE = 'Asia/Manila';

    public const STATUS_RESERVED = 'reserved';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_MOVED_OUT = 'moved_out';

    public const STATUS_LABELS = [
        self::STATUS_RESERVED => 'Reserved',
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_INACTIVE => 'Inactive',
        self::STATUS_MOVED_OUT => 'Moved Out',
    ];

    public const EMERGENCY_RELATIONSHIPS = [
        'mother' => 'Mother',
        'father' => 'Father',
        'sibling' => 'Sibling',
        'guardian' => 'Guardian',
        'other' => 'Other',
    ];

    protected $fillable = [
        'user_id', 'name', 'first_name', 'middle_name', 'last_name', 'email', 'contact_number',
        'emergency_contact_name', 'emergency_contact_number',
        'guardian_name', 'guardian_contact_number', 'move_in_date', 'status',
        'home_address', 'emergency_contact_relationship', 'security_deposit', 'advance_payment',
        'data_privacy_consent_at', 'consent_recorded_by',
    ];

    protected $casts = [
        'move_in_date' => 'date',
        'security_deposit' => 'decimal:2',
        'advance_payment' => 'decimal:2',
        'data_privacy_consent_at' => 'datetime',
        // RA 10173 compliance (Sec. 3.3): guardian numbers encrypted at rest.
        // Uses APP_KEY — back up your .env's APP_KEY, changing it makes this unreadable.
        'guardian_contact_number' => 'encrypted',
    ];

    public static function localDate(): string
    {
        return Carbon::today(self::LOCAL_TIMEZONE)->toDateString();
    }

    public function actualMoveInDate(): ?Carbon
    {
        return $this->status === self::STATUS_RESERVED ? null : $this->move_in_date;
    }

    public function canBeReserved(): bool
    {
        return $this->status !== self::STATUS_ACTIVE && $this->actualMoveInDate() === null;
    }

    public function hasMovedIn(): bool
    {
        $moveIn = $this->actualMoveInDate();

        return $moveIn !== null && $moveIn->toDateString() <= self::localDate();
    }

    public function scopeMovedIn(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_RESERVED)
            ->whereDate('move_in_date', '<=', self::localDate());
    }

    public function consentRecorder()
    {
        return $this->belongsTo(User::class, 'consent_recorded_by');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getNameAttribute(?string $value): string
    {
        return $value ?? $this->user?->name ?? 'Unknown tenant';
    }

    public function getEmailAttribute(?string $value): ?string
    {
        return $value ?? $this->user?->email;
    }

    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value === null ? null : Str::lower(trim($value));
    }

    public function getContactNumberAttribute(?string $value): ?string
    {
        return $value ?? $this->user?->contact_number;
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
