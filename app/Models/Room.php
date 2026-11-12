<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Room extends Model
{
    protected $fillable = ['room_number', 'floor', 'capacity', 'monthly_rate', 'status', 'type', 'rent_history'];

    protected $casts = [
        'monthly_rate' => 'decimal:2',
        'rent_history' => 'array',
    ];

    public static function availableTypes(): array
    {
        $customTypes = self::query()->whereNotNull('type')->distinct()->orderBy('type')->pluck('type')
            ->map(fn (string $type) => self::normalizeType($type))->filter()->all();

        return array_values(array_unique([...array_keys(config('dorm.room_types')), ...$customTypes]));
    }

    public static function normalizeType(?string $type): ?string
    {
        $type = Str::upper(Str::squish($type ?? ''));

        return match ($type) {
            '' => null,
            'SUPEREME' => 'SUPREME',
            default => $type,
        };
    }

    public function catalogType(): ?string
    {
        $type = self::normalizeType($this->type);
        if ($type !== null) {
            return $type;
        }

        $legacyType = self::normalizeType($this->legacyRoomParts()[1] ?? null);

        return isset(config('dorm.room_types')[$legacyType]) ? $legacyType : null;
    }

    public function catalogNumber(): string
    {
        $number = trim($this->room_number);
        $type = $this->catalogType();
        $legacy = $this->legacyRoomParts();

        if ($type !== null && self::normalizeType($legacy[1] ?? null) === $type) {
            return self::normalizeNumber($legacy[2] ?? '1');
        }

        if ($type !== null && str_starts_with($number, $type.' ')) {
            $number = substr($number, strlen($type) + 1);
        }

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
