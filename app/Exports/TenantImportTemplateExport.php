<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TenantImportTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            [
                'Juan Dela Cruz',
                'juan.delacruz@example.com',
                '09171234567',
                'Maria Dela Cruz',
                '09179876543',
                'Pedro Dela Cruz',
                '09161234567',
                '2026-06-01',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'name', 'email', 'contact_number',
            'emergency_contact_name', 'emergency_contact_number',
            'guardian_name', 'guardian_contact_number',
            'move_in_date',
        ];
    }
}