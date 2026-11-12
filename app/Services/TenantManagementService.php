<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\PhilippineMobile;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantManagementService
{
    public function __construct(private readonly TenantPasswordGenerator $passwordGenerator)
    {
    }

    public function validateNewLogin(array $validated): void
    {
        if (! $this->loginEmailInUse($validated['email'])) {
            $this->passwordGenerator->validate($validated['last_name'], $validated['contact_number']);
        }
    }

    public function create(array $validated, int $recordedBy): Tenant
    {
        return DB::transaction(function () use ($validated, $recordedBy) {
            $bed = $this->lockAvailableBed((int) $validated['bed_id'], $validated);
            $attributes = $this->userAttributes($validated);
            $user = $this->createLogin($attributes);

            $tenant = Tenant::create([
                ...$attributes,
                ...$this->tenantAttributes($validated),
                'user_id' => $user?->id,
                'data_privacy_consent_at' => now(),
                'consent_recorded_by' => $recordedBy,
            ]);
            $this->assignBed($tenant, $bed, $tenant->actualMoveInDate()?->toDateString() ?? Tenant::localDate());

            return $tenant;
        }, 3);
    }

    public function update(Tenant $tenant, array $validated, ?int $recordedBy = null): bool
    {
        return DB::transaction(function () use ($tenant, $validated, $recordedBy) {
            $tenant = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            if ($validated['status'] === Tenant::STATUS_RESERVED && ! $tenant->canBeReserved()) {
                throw ValidationException::withMessages(['status' => 'This tenant cannot be changed back to Reserved.']);
            }

            $previousMoveIn = $tenant->actualMoveInDate()?->toDateString();
            $assignment = $tenant->currentAssignment()->lockForUpdate()->first();

            $attributes = $this->userAttributes($validated);
            $loginEmailSkipped = $this->updateLogin($tenant, $attributes);

            $tenant->fill([...$attributes, ...$this->tenantAttributes($validated, $previousMoveIn)]);
            if (! $tenant->data_privacy_consent_at && ! empty($validated['data_privacy_consent'])) {
                $tenant->fill(['data_privacy_consent_at' => now(), 'consent_recorded_by' => $recordedBy]);
            }
            $tenant->save();

            $bedId = array_key_exists('bed_id', $validated) ? $validated['bed_id'] : $assignment?->bed_id;
            $this->reassignBed($tenant, $assignment, $bedId === null ? null : (int) $bedId, $previousMoveIn, $validated);

            return $loginEmailSkipped;
        }, 3);
    }

    public function delete(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant) {
            $user = $tenant->user;
            $user ? $user->delete() : $tenant->delete();
        });
    }

    private function createLogin(array $attributes): ?User
    {
        if ($this->loginEmailInUse($attributes['email'])) {
            return null;
        }

        $password = $this->passwordGenerator->generate($attributes['last_name'], $attributes['contact_number']);

        try {
            return DB::transaction(fn () => User::create([
                ...$attributes,
                'password' => Hash::make($password),
                'role' => 'tenant',
                'must_change_password' => true,
            ]));
        } catch (UniqueConstraintViolationException) {
            // The unique login index resolves simultaneous claims to the same email.
            return null;
        }
    }

    /** Returns whether the tenant email changed but the login email was kept. */
    private function updateLogin(Tenant $tenant, array $attributes): bool
    {
        $user = $tenant->user;
        if (! $user) {
            return false;
        }

        $user->update(Arr::except($attributes, ['email']));
        $email = $attributes['email'];
        if ($email === $tenant->email || $email === $user->email) {
            return false;
        }

        if ($this->loginEmailInUse($email, $user->id)) {
            return true;
        }

        try {
            DB::transaction(fn () => $user->update(['email' => $email]));

            return false;
        } catch (UniqueConstraintViolationException) {
            return true;
        }
    }

    private function loginEmailInUse(string $email, ?int $exceptUserId = null): bool
    {
        return User::whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->when($exceptUserId, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->exists();
    }

    private function userAttributes(array $validated): array
    {
        $names = Arr::only($validated, ['first_name', 'middle_name', 'last_name']);

        return [
            ...$names,
            'name' => trim(preg_replace('/\s+/', ' ', implode(' ', [$names['first_name'], $names['middle_name'] ?? '', $names['last_name']]))),
            'middle_name' => $names['middle_name'] ?? null,
            'email' => Str::lower(trim($validated['email'])),
            ...$this->phoneAttributes($validated, ['contact_number']),
        ];
    }

    private function tenantAttributes(array $validated, ?string $previousMoveIn = null): array
    {
        $attributes = Arr::only($validated, [
            'home_address', 'emergency_contact_name', 'emergency_contact_relationship',
            'guardian_name', 'status', 'security_deposit', 'advance_payment',
        ]);

        $attributes['move_in_date'] = match ($validated['status']) {
            Tenant::STATUS_ACTIVE => $validated['move_in_date'],
            Tenant::STATUS_RESERVED => null,
            default => $previousMoveIn === null ? null : ($validated['move_in_date'] ?? $previousMoveIn),
        };

        foreach (['security_deposit', 'advance_payment'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $attributes[$field] ??= 0;
            }
        }

        return [...$attributes, ...$this->phoneAttributes($validated, ['emergency_contact_number', 'guardian_contact_number'])];
    }

    private function phoneAttributes(array $validated, array $fields): array
    {
        $phones = Arr::only($validated, $fields);

        return array_map(fn ($phone) => PhilippineMobile::normalize($phone) ?? $phone, $phones);
    }

    private function lockAvailableBed(int $bedId, array $selection): Bed
    {
        $bed = Bed::whereKey($bedId)->lockForUpdate()->first();

        if (! $bed || $bed->getRawOriginal('status') !== 'available' || $bed->currentAssignment()->lockForUpdate()->first()) {
            throw ValidationException::withMessages([
                'bed_id' => 'The selected bed is no longer available. Please choose another.',
            ]);
        }

        $this->validateBedSelection($bed, $selection);

        return $bed;
    }

    private function validateBedSelection(Bed $bed, array $selection): void
    {
        if (! isset($selection['room_id'], $selection['room_type'])) {
            return;
        }

        $type = $bed->room?->catalogType() ?? RoomAvailability::LEGACY_TYPE;
        if ((int) $bed->room_id !== (int) $selection['room_id'] || $type !== $selection['room_type']) {
            throw ValidationException::withMessages([
                'bed_id' => 'The selected bed does not belong to that room and room type.',
            ]);
        }
    }

    private function assignBed(Tenant $tenant, Bed $bed, string $startDate): void
    {
        RoomAssignment::create([
            'tenant_id' => $tenant->id,
            'bed_id' => $bed->id,
            'start_date' => $startDate,
            'status' => 'active',
        ]);
    }

    private function reassignBed(Tenant $tenant, ?RoomAssignment $assignment, ?int $bedId, ?string $previousMoveIn, array $selection): void
    {
        $moveIn = $tenant->actualMoveInDate()?->toDateString();
        if ($assignment?->bed_id === $bedId) {
            if ($assignment && isset($selection['room_id'], $selection['room_type'])) {
                $this->validateBedSelection($assignment->bed()->lockForUpdate()->firstOrFail(), $selection);
            }
            if ($assignment && $moveIn && $moveIn !== $previousMoveIn && ($previousMoveIn === null || $assignment->start_date->toDateString() === $previousMoveIn)) {
                $assignment->update(['start_date' => $moveIn]);
            }

            return;
        }

        $bed = $bedId === null ? null : $this->lockAvailableBed($bedId, $selection);
        $today = Tenant::localDate();
        if ($assignment) {
            // A released pre-move-in hold has no billable days.
            $endDate = $previousMoveIn === null ? $assignment->start_date->copy()->subDay()->toDateString() : $today;
            $assignment->update(['status' => 'ended', 'end_date' => $endDate]);
        }

        if ($bed) {
            $startDate = $previousMoveIn === null && $moveIn ? $moveIn : max($today, $moveIn ?? $today);
            $this->assignBed($tenant, $bed, $startDate);
        }
    }
}
