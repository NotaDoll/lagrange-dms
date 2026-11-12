<?php

namespace App\Imports;

use App\Http\Requests\StoreTenantRequest;
use App\Models\Bed;
use App\Models\Room;
use App\Models\Tenant;
use App\Rules\PhilippineMobile;
use App\Services\TenantManagementService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Filters\ChunkReadFilter;
use Maatwebsite\Excel\Row;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TenantsImport
{
    public const MAX_ROWS = 200;

    public const COLUMNS = [
        'first_name', 'middle_name', 'last_name', 'email', 'contact_number', 'home_address',
        'room_type', 'room_number', 'bed', 'status', 'move_in_date', 'security_deposit', 'advance_payment',
        'emergency_contact_name', 'emergency_contact_number', 'emergency_contact_relationship',
        'guardian_name', 'guardian_number',
    ];

    private const OPTIONAL_COLUMNS = [
        'middle_name', 'move_in_date', 'security_deposit', 'advance_payment', 'guardian_name', 'guardian_number',
    ];

    private ?array $headers = null;
    private ?Collection $rooms = null;
    private array $firstBedRows = [];
    private array $validEmails = [];
    private array $valid = [];
    private array $errors = [];
    private int $total = 0;

    public function __construct(private readonly TenantManagementService $tenants)
    {
    }

    public function readWorkbook(string $path, string $format): void
    {
        $reader = IOFactory::createReader($format);
        $worksheet = $reader->listWorksheetInfo($path)[0] ?? null;
        if ($worksheet === null) {
            return;
        }

        $name = $worksheet['worksheetName'];
        $reader->setLoadSheetsOnly($name);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        $chunkSize = 512;
        for ($start = 1; $start <= $worksheet['totalRows']; $start += $chunkSize) {
            $reader->setReadFilter(new ChunkReadFilter(1, $start, $chunkSize, $name));
            $workbook = $reader->load($path);
            try {
                $sheet = $workbook->getSheet(0);
                $end = min($start + $chunkSize - 1, $sheet->getHighestDataRow());
                if ($end < $start) {
                    continue;
                }

                foreach ($sheet->getRowIterator($start, $end) as $row) {
                    $index = $row->getRowIndex();
                    $cells = (new Row($row))->toArray(null, false, false, $sheet->getHighestDataColumn($index));
                    $this->readRow($index, $cells);
                }
            } finally {
                $workbook->disconnectWorksheets();
            }
        }
    }

    public function readCsv(string $path): void
    {
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw ValidationException::withMessages(['file' => 'The uploaded CSV could not be read.']);
        }

        try {
            if (fread($stream, 3) !== "\xEF\xBB\xBF") {
                rewind($stream);
            }

            $row = 0;
            while (($cells = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $this->readRow(++$row, $cells);
            }
        } finally {
            fclose($stream);
        }
    }

    public function preview(): array
    {
        if ($this->total === 0) {
            throw ValidationException::withMessages(['file' => 'The file does not contain any tenant rows.']);
        }

        return ['total' => $this->total, 'valid' => $this->valid, 'errors' => $this->errors];
    }

    public static function errorsFor(int $row, array $messages): array
    {
        $errors = [];
        foreach ($messages as $field => $reasons) {
            $column = match ($field) {
                'bed_id' => 'bed',
                'room_id' => 'room_number',
                'guardian_contact_number' => 'guardian_number',
                default => $field,
            };

            foreach ($reasons as $reason) {
                $errors[] = ['row' => $row, 'column' => $column, 'reason' => $reason];
            }
        }

        return $errors;
    }

    private function readRow(int $row, array $cells): void
    {
        $cells = array_map(fn ($value) => trim((string) $value), $cells);
        if (array_filter($cells, fn (string $value) => $value !== '') === []) {
            return;
        }

        if (! mb_check_encoding($cells, 'UTF-8')) {
            throw ValidationException::withMessages(['file' => 'Save the CSV with UTF-8 encoding and upload it again.']);
        }

        if ($this->headers === null) {
            $this->readHeaders($cells);

            return;
        }

        if (++$this->total > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'The file contains more than 200 tenant rows. Split it into smaller files.']);
        }

        $data = [];
        foreach ($this->headers as $index => $column) {
            $data[$column] = ($cells[$index] ?? '') !== '' ? $cells[$index] : null;
            unset($cells[$index]);
        }

        $messages = [];
        if (array_filter($cells, fn (string $value) => $value !== '') !== []) {
            $messages['row'][] = 'This row has data outside the named template columns.';
        }

        $bed = $this->findBed($data, $messages);
        if ($bed) {
            if (isset($this->firstBedRows[$bed->id])) {
                $messages['bed'][] = 'Bed already used in row '.$this->firstBedRows[$bed->id];
            } else {
                $this->firstBedRows[$bed->id] = $row;
            }

            if ($bed->status !== 'available') {
                $messages['bed'][] = $bed->status === 'occupied' ? 'Bed is occupied or reserved' : 'Bed is unavailable';
            }

            $data['room_type'] = $bed->room->catalogType();
            $data['room_id'] = $bed->room_id;
            $data['bed_id'] = $bed->id;
        }

        $data = $this->normalize($data);
        if ($data['status'] === Tenant::STATUS_RESERVED && filled($data['move_in_date'] ?? null)) {
            $messages['move_in_date'][] = 'Leave the move-in date blank for a Reserved tenant.';
            $data['move_in_date'] = null;
        }

        $request = new StoreTenantRequest();
        $rules = $request->rules();
        if (! $bed) {
            unset($rules['room_type'], $rules['room_id'], $rules['bed_id']);
        }

        $validator = Validator::make($data, $rules, $request->messages(), [
            'guardian_contact_number' => 'guardian number',
        ]);
        $invalid = $validator->fails();
        $errors = array_merge(self::errorsFor($row, $messages), self::errorsFor($row, $validator->errors()->toArray()));

        if (! $invalid && $errors === []) {
            $validated = $validator->validated();
            try {
                if (! isset($this->validEmails[$validated['email']])) {
                    $this->tenants->validateNewLogin($validated);
                }

                $this->validEmails[$validated['email']] = true;
                $this->valid[] = ['row' => $row, 'data' => $validated];

                return;
            } catch (ValidationException $exception) {
                $errors = self::errorsFor($row, $exception->errors());
            }
        }

        array_push($this->errors, ...$errors);
    }

    private function readHeaders(array $cells): void
    {
        $headers = array_filter(array_map(fn (string $value) => Str::lower($value), $cells), fn (string $value) => $value !== '');
        if (in_array('name', $headers, true) && ! in_array('last_name', $headers, true)) {
            throw ValidationException::withMessages(['file' => 'This looks like the old template. Please download the new template.']);
        }

        $missing = array_diff(self::COLUMNS, self::OPTIONAL_COLUMNS, $headers);
        if ($missing !== []) {
            throw ValidationException::withMessages(['file' => 'Missing required columns: '.implode(', ', $missing).'.']);
        }

        if (count($headers) !== count(array_unique($headers)) || array_diff($headers, self::COLUMNS) !== []) {
            throw ValidationException::withMessages(['file' => 'The file contains duplicate or unrecognized column headers. Use the new template.']);
        }

        $this->headers = $headers;
    }

    private function findBed(array $data, array &$messages): ?Bed
    {
        $type = strtoupper($data['room_type'] ?? '');
        if (! array_key_exists($type, config('dorm.room_types'))) {
            $messages['room_type'][] = 'Unknown room type';
        }
        if (blank($data['room_number'] ?? null)) {
            $messages['room_number'][] = 'Enter the room number.';
        }
        if (blank($data['bed'] ?? null)) {
            $messages['bed'][] = 'Enter the bed label.';
        }
        if (isset($messages['room_type']) || isset($messages['room_number']) || isset($messages['bed'])) {
            return null;
        }

        $number = Room::normalizeNumber($data['room_number']);
        $this->rooms ??= Room::with('beds.currentAssignment')->get()
            ->groupBy(fn (Room $room) => $room->catalogType().':'.$room->catalogNumber());
        $rooms = $this->rooms->get($type.':'.$number, collect());
        if ($rooms->count() !== 1) {
            $messages['room_number'][] = $rooms->isEmpty()
                ? "Room $number does not exist in $type"
                : "Room $number is ambiguous in $type";

            return null;
        }

        $room = $rooms->first();
        $label = Bed::normalizeLabel($data['bed']);
        $beds = $room->beds->filter(fn (Bed $bed) => Bed::normalizeLabel($bed->bed_label) === $label);
        if ($beds->count() !== 1) {
            $messages['bed'][] = $beds->isEmpty() ? "Bed $label does not exist" : "Bed $label is ambiguous";

            return null;
        }

        return $beds->first()->setRelation('room', $room);
    }

    private function normalize(array $data): array
    {
        $data['email'] = Str::lower($data['email'] ?? '');
        $data['status'] = Str::lower($data['status'] ?? '');
        $data['emergency_contact_relationship'] = Str::lower($data['emergency_contact_relationship'] ?? '');
        $data['guardian_contact_number'] = $data['guardian_number'] ?? null;
        $data['data_privacy_consent'] = true;

        foreach (['contact_number', 'emergency_contact_number', 'guardian_contact_number'] as $field) {
            $number = $data[$field] ?? null;
            $data[$field] = PhilippineMobile::normalize($number, allowMissingZero: true) ?? $number;
        }

        foreach (['security_deposit', 'advance_payment'] as $field) {
            $data[$field] = isset($data[$field]) ? trim(str_replace(['₱', ','], '', $data[$field])) : null;
        }

        return $data;
    }
}
