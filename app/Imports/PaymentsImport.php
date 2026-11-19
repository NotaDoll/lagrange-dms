<?php

namespace App\Imports;

use App\Models\Tenant;
use App\Services\PaymentLedgerService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class PaymentsImport implements ToCollection, WithHeadingRow
{
    protected PaymentLedgerService $service;
    protected int $successCount = 0;
    protected array $errors = [];

    public function __construct(PaymentLedgerService $service)
    {
        $this->service = $service;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            // Skip fully blank rows.
            if (empty($row['tenant_id']) && empty($row['tenant_email']) && empty($row['amount'])) {
                continue;
            }

            try {
                // Use tenant_id if given, otherwise look the tenant up by email.
                $tenantId = $row['tenant_id'] ?? null;

                if (empty($tenantId) && !empty($row['tenant_email'])) {
                    $email = strtolower(trim($row['tenant_email']));
                    $tenantId = Tenant::where('email', $email)->value('id');

                    if (!$tenantId) {
                        throw new \Exception("No tenant found with email {$email}");
                    }
                }

                if (empty($tenantId)) {
                    throw new \Exception('Provide either tenant_id or tenant_email.');
                }

                $dueDate = is_numeric($row['due_date'])
                    ? Date::excelToDateTimeObject($row['due_date'])->format('Y-m-d')
                    : $row['due_date'];

                $paidDate = null;
                if (!empty($row['paid_date'])) {
                    $paidDate = is_numeric($row['paid_date'])
                        ? Date::excelToDateTimeObject($row['paid_date'])->format('Y-m-d')
                        : $row['paid_date'];
                }

                $validatedData = [
                    'tenant_id' => $tenantId,
                    'amount'    => $row['amount'],
                    'due_date'  => $dueDate,
                    'status'    => strtolower($row['status']),
                    'paid_date' => $paidDate,
                    // Optional column; blank paid rows get a generated receipt number.
                    'receipt_number' => isset($row['receipt_number']) ? trim((string) $row['receipt_number']) : null,
                ];

                if ($validatedData['status'] !== 'paid') {
                    $validatedData['paid_date'] = null;
                }

                $this->service->create($validatedData);
                $this->successCount++;

            } catch (ValidationException $e) {
                $rowNumber = $index + 2;
                $who = $row['tenant_email'] ?? $row['tenant_id'] ?? 'unknown';
                $this->errors[] = "Row {$rowNumber} ({$who}): " . implode(', ', $e->validator->errors()->all());
            } catch (\Exception $e) {
                $rowNumber = $index + 2;
                $this->errors[] = "Row {$rowNumber}: " . $e->getMessage();
            }
        }
    }

    public function getSuccessCount(): int { return $this->successCount; }
    public function getErrors(): array { return $this->errors; }
}
