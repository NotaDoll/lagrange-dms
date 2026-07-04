<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomAssignmentService
{
    public function assign(int $tenantId, int $bedId): RoomAssignment
    {
        return DB::transaction(function () use ($tenantId, $bedId) {
            $tenant = Tenant::whereKey($tenantId)->lockForUpdate()->firstOrFail();
            $bed = Bed::whereKey($bedId)->lockForUpdate()->firstOrFail();

            if ($bed->status === 'occupied' || $bed->currentAssignment()->exists()) {
                throw ValidationException::withMessages([
                    'bed_id' => 'This bed is already occupied.',
                ]);
            }

            if ($tenant->currentAssignment()->exists()) {
                throw ValidationException::withMessages([
                    'tenant_id' => 'This tenant already has an active assignment.',
                ]);
            }

            $bed->update(['status' => 'occupied']);

            return RoomAssignment::create([
                'tenant_id' => $tenant->id,
                'bed_id' => $bed->id,
                'start_date' => Carbon::today(),
                'status' => 'active',
            ]);
        });
    }

    public function end(RoomAssignment $roomAssignment): RoomAssignment
    {
        return DB::transaction(function () use ($roomAssignment) {
            $assignment = RoomAssignment::whereKey($roomAssignment->id)->lockForUpdate()->firstOrFail();

            if ($assignment->status === 'ended') {
                return $assignment;
            }

            $assignment->update([
                'end_date' => Carbon::today(),
                'status' => 'ended',
            ]);

            $assignment->bed()->update(['status' => 'available']);

            return $assignment->refresh();
        });
    }
}
