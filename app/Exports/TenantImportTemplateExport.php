<?php

namespace App\Exports;

use App\Imports\TenantsImport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class TenantImportTemplateExport extends StringValueBinder implements FromArray, WithHeadings, WithCustomCsvSettings, WithCustomValueBinder
{
    public function array(): array
    {
        return [
            ['Ana', 'Santos', 'Dela Cruz', 'ana@example.com', '09171234567', '12 Mabini St, Cebu City', 'CABIN', '2', 'A', 'active', '2026-08-01', '5000.00', '0.00', 'Maria Dela Cruz', '09179876543', 'mother', '', ''],
            ['Lina', '', 'Reyes', 'lina@example.com', '09181234567', '28 Rizal St, Cebu City', 'SUITE', '1', 'B', 'reserved', '', '0.00', '0.00', 'Rosa Reyes', '09189876543', 'guardian', 'Rosa Reyes', '09189876543'],
        ];
    }

    public function headings(): array
    {
        return TenantsImport::COLUMNS;
    }

    public function getCsvSettings(): array
    {
        return ['use_bom' => true, 'output_encoding' => 'UTF-8'];
    }
}
