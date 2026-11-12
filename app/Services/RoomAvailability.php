<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\Room;
use App\Models\Tenant;

class RoomAvailability
{
    public const LEGACY_TYPE = 'legacy';

    public function forTenant(?Tenant $tenant = null): array
    {
        $currentBed = $tenant?->currentAssignment?->bed;
        $currentBedId = $currentBed?->id;
        $types = [];
        foreach (config('dorm.room_types') as $type => $structure) {
            $types[$type] = ['label' => $type, 'rate' => $structure['monthly_rate'], 'rooms' => []];
        }

        foreach (Room::with('beds.currentAssignment')->get()->sortBy(fn (Room $room) => (int) $room->catalogNumber()) as $room) {
            $type = $room->catalogType();
            if ($type === null) {
                if ($currentBed?->room_id !== $room->id) {
                    continue;
                }

                $type = self::LEGACY_TYPE;
                $types[$type] = ['label' => 'Current room', 'rate' => (float) $room->monthly_rate, 'rooms' => []];
            }

            $types[$type] ??= ['label' => $type, 'rate' => (float) $room->monthly_rate, 'rooms' => []];

            $beds = $room->beds->filter(fn (Bed $bed) => $bed->status === 'available' || $bed->id === $currentBedId);
            $types[$type]['rooms'][] = [
                'id' => $room->id,
                'number' => $room->catalogNumber(),
                'rate' => (float) $room->monthly_rate,
                'free' => $beds->filter(fn (Bed $bed) => $bed->status === 'available')->count(),
                'beds' => $beds->sortBy(fn (Bed $bed) => Bed::normalizeLabel($bed->bed_label), SORT_NATURAL)
                    ->map(fn (Bed $bed) => [
                        'id' => $bed->id,
                        'label' => $bed->displayLabel(),
                        'current' => $bed->id === $currentBedId,
                    ])->values()->all(),
            ];
        }

        foreach ($types as &$type) {
            $selectable = array_filter($type['rooms'], fn (array $room) => $room['beds'] !== []);
            $rates = array_unique(array_column($selectable, 'rate'));
            $type['selectable'] = $selectable !== [];
            $type['rates'] = $rates === [] ? [$type['rate']] : [min($rates), max($rates)];
        }
        unset($type);

        return $types;
    }
}
