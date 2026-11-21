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
    /** The tenant has moved out: their bed is released and billing stops. */
    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_LABELS = [
        self::STATUS_RESERVED => 'Reserved',
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_INACTIVE => 'Inactive (Moved Out)',
    ];

    /** How the guardian is related to the tenant. */
    public const GUARDIAN_RELATIONSHIPS = [
        'mother' => 'Mother',
        'father' => 'Father',
        'sibling' => 'Sibling',
        'guardian' => 'Legal guardian',
        'other' => 'Other relative',
    ];

    protected $fillable = [
        'user_id', 'name', 'first_name', 'middle_name', 'last_name', 'email', 'contact_number',
        'guardian_name', 'guardian_contact_number', 'guardian_relationship', 'move_in_date', 'status',
        'home_address', 'security_deposit', 'advance_payment',
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

    public function monthModes()
    {
        return $this->hasMany(TenantMonthMode::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }

    public function smsLogs()
    {
        return $this->hasMany(SmsLog::class);
    }

    // ==========================================
    // NEW: MUTATORS FOR PROPER NAME FORMATTING
    // ==========================================

    /**
     * Automatically format the first name to Title Case.
     */
    public function setFirstNameAttribute(?string $value): void
    {
        $this->attributes['first_name'] = $value === null ? null : $this->formatName($value);
    }

    /**
     * Automatically format the middle name to Title Case.
     */
    public function setMiddleNameAttribute(?string $value): void
    {
        $this->attributes['middle_name'] = $value === null ? null : $this->formatName($value);
    }

    /**
     * Automatically format the last name to Title Case.
     */
    public function setLastNameAttribute(?string $value): void
    {
        $this->attributes['last_name'] = $value === null ? null : $this->formatName($value);
    }

    /**
     * Helper to format names consistently.
     */
    private function formatName(string $value): string
    {
        return ucwords(strtolower(trim($value)));
    }
}
