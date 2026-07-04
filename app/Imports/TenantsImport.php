<?php

namespace App\Imports;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TempPasswordGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Row;

class TenantsImport implements OnEachRow, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    /** @var array<int, array{row:int, name:string, email:string, password:string}> */
    public array $created = [];

    /** @var array<int, array{row:int, reason:string}> */
    public array $skipped = [];

    public function __construct(private readonly TempPasswordGenerator $passwordGenerator)
    {
    }

    public function onRow(Row $row): void
    {
        $rowNumber = $row->getIndex();
        $data = $row->toArray();

        if (User::where('email', $data['email'])->exists()) {
            $this->skipped[] = ['row' => $rowNumber, 'reason' => "Email {$data['email']} already exists"];
            return;
        }

        $tempPassword = $this->passwordGenerator->fromFullName($data['name'], $data['contact_number'] ?? null);

        DB::transaction(function () use ($data, $tempPassword) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($tempPassword),
                'role' => 'tenant',
                'contact_number' => $data['contact_number'] ?? null,
            ]);

            Tenant::create([
                'user_id' => $user->id,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_number' => $data['emergency_contact_number'] ?? null,
                'guardian_name' => $data['guardian_name'] ?? null,
                'guardian_contact_number' => $data['guardian_contact_number'] ?? null,
                'move_in_date' => $data['move_in_date'] ?? null,
                'status' => 'active',
            ]);
        });

        $this->created[] = [
            'row' => $rowNumber,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $tempPassword,
        ];
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'contact_number' => 'nullable|string|max:20',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_number' => 'nullable|string|max:20',
            'guardian_name' => 'nullable|string|max:255',
            'guardian_contact_number' => 'nullable|string|max:20',
            'move_in_date' => 'nullable|date',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Email must be a valid email address.',
        ];
    }
}