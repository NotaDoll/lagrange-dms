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

    public static function letterLabel(int $position): string
    {
        $label = '';
        while ($position > 0) {
            $position--;
            $label = chr(65 + $position % 26).$label;
            $position = intdiv($position, 26);
        }

        return $label;
    }

    public static function normalizeLabel(string $label): string
    {
        $label = strtoupper(preg_replace('/^(?:BED\s+)+/i', '', trim($label)));

        return ctype_digit($label) && (int) $label > 0 && (int) $label <= 255
            ? self::letterLabel((int) $label)
            : $label;
    }

    public function displayLabel(): string
    {
        return 'Bed '.self::normalizeLabel($this->bed_label);
    }

    public function locationLabel(): string
    {
        return $this->room ? $this->room->displayName().' - '.$this->displayLabel() : $this->displayLabel();
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
