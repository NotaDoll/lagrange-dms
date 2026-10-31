<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomManagementService
{
    public function create(array $validated): Room
    {
        return DB::transaction(function () use ($validated) {
            $room = Room::create([
                'room_number' => $validated['room_number'],
                'floor' => $validated['floor'],
                'capacity' => $validated['capacity'],
                'monthly_rate' => $validated['monthly_rate'],
                'status' => 'available',
            ]);

            $this->syncBeds($room, (int) $validated['capacity']);

            return $room;
        });
    }

    public function update(Room $room, array $validated): Room
    {
        return DB::transaction(function () use ($room, $validated) {
            $room->update([
                'room_number' => $validated['room_number'],
                'floor' => $validated['floor'],
                'capacity' => $validated['capacity'],
                'monthly_rate' => $validated['monthly_rate'],
            ]);

            $this->syncBeds($room, (int) $validated['capacity']);

            return $room->refresh();
        });
    }

    public function delete(Room $room): void
    {
        DB::transaction(function () use ($room) {
            $room = Room::query()->lockForUpdate()->findOrFail($room->id);

            if ($room->beds()->whereHas('assignments')->exists() || $room->utilityBills()->exists()) {
                throw ValidationException::withMessages([
                    'room' => 'This room has tenant assignment or utility bill history and cannot be deleted.',
                ]);
            }

            $room->delete();
        });
    }

    public function updateBedStatus(Bed $bed, string $status): Bed
    {
        return DB::transaction(function () use ($bed, $status) {
            $bed = Bed::query()->lockForUpdate()->findOrFail($bed->id);

            if ($bed->currentAssignment()->exists() || $bed->status === 'occupied') {
                throw ValidationException::withMessages([
                    'bed' => 'End the active tenant assignment before changing this bed status.',
                ]);
            }

            $bed->update(['status' => $status]);

            return $bed->refresh();
        });
    }

    private function syncBeds(Room $room, int $capacity): void
    {
        $beds = $room->beds()->orderBy('id')->get();

        if ($capacity > $beds->count()) {
            for ($number = $beds->count() + 1; $number <= $capacity; $number++) {
                $room->beds()->create([
                    'bed_label' => 'Bed ' . chr(64 + $number),
                    'status' => 'available',
                ]);
            }

            return;
        }

        if ($capacity < $beds->count()) {
            $bedsToRemove = $beds->slice($capacity);
            $unavailableBed = $bedsToRemove->first(function (Bed $bed) {
                return $bed->status === 'occupied' || $bed->assignments()->exists();
            });

            if ($unavailableBed) {
                throw ValidationException::withMessages([
                    'capacity' => 'Capacity cannot remove occupied beds or beds with tenant assignment history.',
                ]);
            }

            $bedsToRemove->each->delete();
        }
    }
}
