<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = ['room_number', 'floor', 'capacity', 'monthly_rate', 'status', 'type', 'rent_history'];

    protected $casts = [
        'monthly_rate' => 'decimal:2',
        'rent_history' => 'array',
    ];

    public static function normalizeType(?string $type): ?string
    {
        $type = strtoupper(trim($type ?? ''));
        $type = $type === 'SUPEREME' ? 'SUPREME' : $type;

        return array_key_exists($type, config('dorm.room_types')) ? $type : null;
    }

    public function catalogType(): ?string
    {
        return self::normalizeType($this->type) ?? self::normalizeType($this->legacyRoomParts()[1] ?? null);
    }

    public function catalogNumber(): string
    {
        $legacy = $this->legacyRoomParts();
        $number = self::normalizeType($legacy[1] ?? null) ? ($legacy[2] ?? '1') : trim($this->room_number);

        return self::normalizeNumber($number);
    }

    public static function normalizeNumber(string $number): string
    {
        $number = trim($number);

        return ctype_digit($number) ? (ltrim($number, '0') ?: '0') : $number;
    }

    public function displayName(): string
    {
        $type = $this->catalogType();

        return $type ? $type.' '.$this->catalogNumber() : 'Room '.$this->room_number;
    }

    public function monthlyRateFor(string $month): float
    {
        foreach ($this->rent_history ?? [] as $previous) {
            if ($month <= $previous['through_month']) {
                return (float) $previous['rate'];
            }
        }

        return (float) $this->monthly_rate;
    }

    private function legacyRoomParts(): array
    {
        preg_match('/^([A-Z]+)(?:\s+([0-9]+))?$/i', trim($this->room_number), $parts);

        return $parts;
    }

    public function beds()
    {
        return $this->hasMany(Bed::class);
    }
    
    public function utilityBills()
{
    return $this->hasMany(UtilityBill::class);
}
}
