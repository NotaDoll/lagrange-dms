<?php

namespace Database\Seeders;

use App\Models\Bed;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RoomStructureSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = config('dorm.room_types');
        $existing = Room::all()->groupBy(fn (Room $room) => $this->key($room->catalogType(), $room->catalogNumber()));
        $matched = [];
        $report = [
            'Rooms created' => [],
            'Beds created' => [],
            'Rooms changed' => [],
            'Protected rooms skipped' => [],
            'Unmatched rooms and beds' => [],
        ];

        foreach ($catalog as $type => $structure) {
            for ($number = 1; $number <= $structure['room_count']; $number++) {
                $name = $type.' '.$number;
                $matches = $existing->get($this->key($type, (string) $number), collect());
                foreach ($matches as $match) {
                    $matched[$match->id] = true;
                }

                if ($matches->count() > 1) {
                    $report['Unmatched rooms and beds'][] = "$name has multiple matches (room IDs ".$matches->pluck('id')->implode(', ').'); skipped.';
                    continue;
                }

                $changes = DB::transaction(
                    fn () => $this->syncRoom($type, (string) $number, $structure, $matches->first()),
                    3
                );
                foreach ($changes as $section => $items) {
                    array_push($report[$section], ...$items);
                }
            }
        }

        foreach ($existing->collapse() as $room) {
            if (! isset($matched[$room->id])) {
                $report['Unmatched rooms and beds'][] = "Room #{$room->id} ({$room->room_number}) is outside the catalog.";
                foreach ($room->beds as $bed) {
                    $report['Unmatched rooms and beds'][] = "Room #{$room->id} / {$bed->bed_label} (bed #{$bed->id}) is outside the catalog.";
                }
            }
        }

        foreach ($report as $section => $items) {
            $this->command?->info($section.' ('.count($items).')');
            foreach ($items as $item) {
                $this->command?->line('  - '.$item);
            }
        }
    }

    private function syncRoom(string $type, string $number, array $structure, ?Room $existing): array
    {
        $name = $type.' '.$number;
        $room = $existing ?? Room::firstOrCreate(['room_number' => $name], [
            'type' => $type,
            'floor' => $structure['floor'],
            'capacity' => $structure['beds_per_room'],
            'monthly_rate' => $structure['monthly_rate'],
            'status' => 'available',
        ]);
        $created = $room->wasRecentlyCreated;
        $room = Room::whereKey($room->id)->lockForUpdate()->firstOrFail();

        if ($room->catalogType() !== $type || $room->catalogNumber() !== $number) {
            return ['Unmatched rooms and beds' => ["$name conflicts with room #{$room->id}; skipped."]];
        }

        $changes = $created ? ['Rooms created' => [$name]] : [];
        $beds = $room->beds()->orderBy('id')->lockForUpdate()->get();
        $expected = [];
        for ($position = 1; $position <= $structure['beds_per_room']; $position++) {
            $expected[] = Bed::letterLabel($position);
        }
        $byLabel = $beds->groupBy(fn (Bed $bed) => Bed::normalizeLabel($bed->bed_label));
        $missing = array_diff($expected, $byLabel->keys()->all());
        foreach ($byLabel as $label => $matches) {
            if (! in_array($label, $expected, true) || $matches->count() > 1) {
                foreach ($matches as $bed) {
                    $changes['Unmatched rooms and beds'][] = "$name / {$bed->bed_label} (bed #{$bed->id}); unexpected or ambiguous label.";
                }
            }
        }

        $held = RoomAssignment::whereIn('bed_id', $beds->modelKeys())->where('status', 'active')
            ->lockForUpdate()->first();
        if ($held || $beds->contains(fn (Bed $bed) => $bed->getRawOriginal('status') === 'occupied')) {
            $missingNote = $missing ? '; missing beds '.implode(', ', $missing) : '';
            $storedType = $room->type ?? '(unset)';
            $changes['Protected rooms skipped'][] = "$name (room #{$room->id}, stored type $storedType, rent {$room->monthly_rate}{$missingNote}) has an occupied or held bed.";

            return $changes;
        }

        $roomChanges = [];
        if ($room->type !== $type) {
            $roomChanges[] = 'type '.($room->type ?? '(unset)').' -> '.$type;
            $room->type = $type;
        }

        if ((float) $room->monthly_rate !== (float) $structure['monthly_rate']) {
            $roomChanges[] = 'rent '.$room->monthly_rate.' -> '.number_format($structure['monthly_rate'], 2, '.', '');
            $this->preserveAccruedRent($room);
            $room->monthly_rate = $structure['monthly_rate'];
        }

        foreach ($missing as $label) {
            $bed = $room->beds()->firstOrCreate(['bed_label' => $label], ['status' => 'available']);
            if ($bed->wasRecentlyCreated) {
                $changes['Beds created'][] = "$name - Bed $label";
            }
        }

        if (! empty($changes['Beds created']) && $beds->count() + count($changes['Beds created']) > $room->capacity) {
            $capacity = $beds->count() + count($changes['Beds created']);
            $roomChanges[] = "capacity {$room->capacity} -> $capacity";
            $room->capacity = $capacity;
        }

        if ($room->isDirty()) {
            $room->save();
            $changes['Rooms changed'][] = $name.': '.implode('; ', $roomChanges);
        }

        return $changes;
    }

    private function preserveAccruedRent(Room $room): void
    {
        $today = Carbon::parse(Tenant::localDate());
        $through = ($today->day === $today->daysInMonth ? $today : $today->copy()->subMonthNoOverflow())->format('Y-m');
        $history = $room->rent_history ?? [];
        $last = end($history);

        if ($last === false || $last['through_month'] < $through) {
            $history[] = ['through_month' => $through, 'rate' => $room->monthly_rate];
            $room->rent_history = $history;
        }
    }

    private function key(?string $type, string $number): string
    {
        return ($type ?? '').':'.$number;
    }
}
