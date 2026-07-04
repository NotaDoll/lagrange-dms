<?php

namespace App\Http\Controllers\Proprietor;

use App\Exports\TenantImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\TenantsImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class TenantImportController extends Controller
{
    public function create(): View
    {
        return view('proprietor.tenants.import');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $import = new TenantsImport();
        Excel::import($import, $request->file('file'));

        return redirect()
            ->route('proprietor.tenants.import.results')
            ->with('import_created', $import->created)
            ->with('import_skipped', $import->skipped)
            ->with('import_failures', $import->failures()->map(fn ($f) => [
                'row' => $f->row(),
                'attribute' => $f->attribute(),
                'reason' => implode(' ', $f->errors()),
            ])->toArray());
    }

    public function results(): View
    {
        return view('proprietor.tenants.import-results', [
            'created' => session('import_created', []),
            'skipped' => session('import_skipped', []),
            'failures' => session('import_failures', []),
        ]);
    }

    public function downloadTemplate()
    {
        return Excel::download(new TenantImportTemplateExport(), 'tenant-import-template.xlsx');
    }
}