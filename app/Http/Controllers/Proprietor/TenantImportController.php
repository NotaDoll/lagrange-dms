<?php

namespace App\Http\Controllers\Proprietor;

use App\Exports\TenantImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\TenantsImport;
use App\Services\TenantManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetReadException;

class TenantImportController extends Controller
{
    public function create(): View
    {
        return view('proprietor.tenants.import');
    }

    public function store(Request $request, TenantManagementService $tenants): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:csv,xlsx,xls', 'mimes:csv,txt,xlsx,xls', 'max:2048'],
            'consent' => ['required', 'accepted'],
        ], [
            'file.max' => 'The upload must be 2 MB or smaller.',
            'file.extensions' => 'Upload a CSV or Excel (.xlsx or .xls) file.',
            'file.mimes' => 'Upload a readable CSV or Excel file.',
            'consent.required' => 'Confirm consent before uploading the file.',
            'consent.accepted' => 'Confirm consent before uploading the file.',
        ]);

        $file = $request->file('file');
        $import = new TenantsImport($tenants);
        $extension = Str::lower($file->getClientOriginalExtension());

        try {
            if ($extension === 'csv') {
                $import->readCsv($file->getPathname());
            } else {
                $import->readWorkbook($file->getPathname(), $extension === 'xls' ? ExcelFormat::XLS : ExcelFormat::XLSX);
            }
        } catch (SpreadsheetReadException) {
            throw ValidationException::withMessages(['file' => 'The spreadsheet could not be read. Check the file and upload it again.']);
        }

        $preview = $import->preview();
        $token = (string) Str::uuid();
        $request->session()->put('tenant_import_preview', [
            'token' => $token,
            'user_id' => $request->user()->id,
            'expires_at' => now()->addMinutes(30)->timestamp,
            'preview' => $preview,
        ]);

        return redirect()->route('proprietor.tenants.import.preview', $token);
    }

    public function preview(Request $request, string $preview)
    {
        $record = $this->findPreview($request, $preview);
        if ($record === null) {
            return $this->missingPreview();
        }

        $data = $record['preview'];

        return response()->view('proprietor.tenants.import-preview', [
            'token' => $preview,
            'total' => $data['total'],
            'valid' => count($data['valid']),
            'errorsByRow' => collect($data['errors'])->groupBy('row'),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function confirm(Request $request, string $preview, TenantManagementService $tenants): RedirectResponse
    {
        $record = $this->takePreview($request, $preview);
        if ($record === null) {
            return $this->missingPreview();
        }

        $data = $record['preview'];
        $result = [
            'total' => $data['total'], 'imported' => 0, 'logins_created' => 0,
            'without_login' => [], 'errors' => $data['errors'],
        ];

        foreach ($data['valid'] as $row) {
            try {
                $tenant = $tenants->create($row['data'], $request->user()->id);
            } catch (ValidationException $exception) {
                array_push($result['errors'], ...TenantsImport::errorsFor($row['row'], $exception->errors()));
                continue;
            } catch (\Throwable $exception) {
                Log::warning('Tenant import row could not be saved.', ['row' => $row['row'], 'exception' => class_basename($exception)]);
                $result['errors'][] = ['row' => $row['row'], 'column' => 'row', 'reason' => 'This row could not be saved. Check the data and try it in a new import.'];
                continue;
            }

            $result['imported']++;
            if ($tenant->user_id) {
                $result['logins_created']++;
            } else {
                $result['without_login'][] = ['row' => $row['row'], 'name' => $tenant->name, 'email' => $tenant->email];
            }
        }

        return redirect()->route('proprietor.tenants.import.results')->with('tenant_import_result', $result);
    }

    public function cancel(Request $request, string $preview): RedirectResponse
    {
        if ($this->takePreview($request, $preview) === null) {
            return $this->missingPreview();
        }

        return redirect()->route('proprietor.tenants.import.create');
    }

    public function results(Request $request)
    {
        $result = $request->session()->get('tenant_import_result');
        if ($result === null) {
            return redirect()->route('proprietor.tenants.import.create');
        }

        return response()->view('proprietor.tenants.import-results', ['result' => $result])
            ->header('Cache-Control', 'private, no-store');
    }

    public function downloadErrors(Request $request, string $preview)
    {
        $record = $this->findPreview($request, $preview);
        if ($record === null) {
            return $this->missingPreview();
        }

        return response()->streamDownload(function () use ($record) {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['row', 'column', 'reason'], ',', '"', '');
            foreach ($record['preview']['errors'] as $error) {
                fputcsv($stream, [$error['row'], $error['column'], $error['reason']], ',', '"', '');
            }
            fclose($stream);
        }, 'tenant-import-errors.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function downloadTemplate()
    {
        return Excel::download(new TenantImportTemplateExport(), 'tenant-import-template.csv', ExcelFormat::CSV);
    }

    private function findPreview(Request $request, string $token): ?array
    {
        $record = $request->session()->get('tenant_import_preview');
        if ($record === null || $record['user_id'] !== $request->user()->id || $record['token'] !== $token) {
            return null;
        }

        if ($record['expires_at'] <= now()->timestamp) {
            $request->session()->forget('tenant_import_preview');

            return null;
        }

        return $record;
    }

    private function takePreview(Request $request, string $token): ?array
    {
        $record = $this->findPreview($request, $token);
        // Claim the token atomically before processing or cancelling any rows.
        if ($record === null || ! Cache::add('tenant-import-claim:'.$token, true, now()->addMinutes(30))) {
            return null;
        }

        $request->session()->forget('tenant_import_preview');

        return $record;
    }

    private function missingPreview(): RedirectResponse
    {
        return redirect()->route('proprietor.tenants.import.create')
            ->withErrors(['file' => 'This preview is no longer available. Upload the file again to continue.']);
    }
}
