<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TenantManagementService
{
    public function __construct(private readonly TempPasswordGenerator $passwordGenerator)
    {
    }

    public function create(array $validated): array
    {
        return DB::transaction(function () use ($validated) {
            $fullName = $this->buildFullName(
                $validated['first_name'],
                $validated['middle_name'] ?? null,
                $validated['last_name'],
            );

            $tempPassword = $this->passwordGenerator->fromNameParts(
                $validated['last_name'],
                $validated['contact_number'] ?? null,
            );

            $user = User::create([
                'name' => $fullName,
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => $tempPassword,
                'role' => 'tenant',
                'contact_number' => $validated['contact_number'] ?? null,
            ]);

            $tenant = Tenant::create([
                'user_id' => $user->id,
                'emergency_contact_name' => $validated['emergency_contact_name'],
                'emergency_contact_number' => $validated['emergency_contact_number'],
                'guardian_name' => $validated['guardian_name'] ?? null,
                'guardian_contact_number' => $validated['guardian_contact_number'] ?? null,
                'move_in_date' => $validated['move_in_date'],
                'status' => $validated['status'],
            ]);

            if (! empty($validated['bed_id'])) {
                $this->assignBed($tenant, $validated['bed_id'], $validated['move_in_date']);
            }

            return ['tenant' => $tenant, 'temp_password' => $tempPassword];
        });
    }

    public function update(Tenant $tenant, array $validated): Tenant
    {
        return DB::transaction(function () use ($tenant, $validated) {
            $fullName = $this->buildFullName(
                $validated['first_name'],
                $validated['middle_name'] ?? null,
                $validated['last_name'],
            );

            $tenant->user->update([
                'name' => $fullName,
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'contact_number' => $validated['contact_number'] ?? null,
            ]);

            $tenant->update([
                'emergency_contact_name' => $validated['emergency_contact_name'],
                'emergency_contact_number' => $validated['emergency_contact_number'],
                'guardian_name' => $validated['guardian_name'] ?? null,
                'guardian_contact_number' => $validated['guardian_contact_number'] ?? null,
                'move_in_date' => $validated['move_in_date'],
                'status' => $validated['status'],
            ]);

            $this->reassignBed($tenant, $validated['bed_id'] ?? null);

            return $tenant->refresh();
        });
    }

    public function delete(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant) {
            $tenant->user->delete();
        });
    }

    private function buildFullName(string $first, ?string $middle, string $last): string
    {
        return trim(preg_replace('/\s+/', ' ', "{$first} {$middle} {$last}"));
    }

    private function assignBed(Tenant $tenant, int $bedId, string $startDate): void
    {
        $bed = Bed::lockForUpdate()->findOrFail($bedId);

        if ($bed->status !== 'available') {
            return;
        }

        RoomAssignment::create([
            'tenant_id' => $tenant->id,
            'bed_id' => $bed->id,
            'start_date' => $startDate,
            'status' => 'active',
        ]);

        $bed->update(['status' => 'occupied']);
    }

    private function reassignBed(Tenant $tenant, ?int $newBedId): void
    {
        $currentAssignment = $tenant->currentAssignment()->first();
        $currentBedId = $currentAssignment?->bed_id;

        if ($currentBedId === $newBedId) {
            return;
        }

        if ($currentAssignment) {
            $currentAssignment->update(['status' => 'ended', 'end_date' => now()]);
            Bed::where('id', $currentBedId)->update(['status' => 'available']);
        }

        if ($newBedId) {
            $bed = Bed::lockForUpdate()->find($newBedId);

            if (! $bed || $bed->status !== 'available') {
                return;
            }

            RoomAssignment::create([
                'tenant_id' => $tenant->id,
                'bed_id' => $bed->id,
                'start_date' => now()->toDateString(),
                'status' => 'active',
            ]);

            $bed->update(['status' => 'occupied']);
        }
    }
}