<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Report</title>
    <style>
        @page { margin: 38pt 42pt 50pt; }
        body { margin: 0; color: #202633; font: 9pt/1.35 "DejaVu Sans", sans-serif; }
        header { border-bottom: 1.5pt solid #252d41; padding-bottom: 11pt; margin-bottom: 12pt; }
        .organization { font-size: 10pt; font-weight: bold; color: #495268; }
        h1 { margin: 3pt 0 9pt; color: #222a3b; font: bold 24pt "DejaVu Serif", serif; }
        h2 { margin: 12pt 0 4pt; font-size: 12pt; color: #222a3b; page-break-after: avoid; }
        p { margin: 0 0 4pt; }
        .context { color: #4c5563; font-size: 8pt; page-break-after: avoid; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        th, td { padding: 3pt 6pt; text-align: left; vertical-align: top; overflow-wrap: break-word; }
        thead th { background: #e9edf4; color: #242e42; font-size: 8pt; border-bottom: 1pt solid #9ea8b9; }
        tbody td, tbody th { border-bottom: .5pt solid #d7dce4; }
        tbody th { font-weight: normal; }
        tfoot td, tfoot th { border-top: 1pt solid #939fb1; font-weight: bold; }
        tr { page-break-inside: avoid; }
        .number { text-align: right; white-space: nowrap; }
        .metadata { color: #4c5563; }
        .summary td { width: 25%; background: #f4f6fa; border: .5pt solid #d7dce4; padding: 7pt 6pt; }
        .summary strong { display: block; margin-top: 3pt; font-size: 11pt; }
        .summary span { font-size: 8pt; }
        .timing { margin-top: 6pt; font-size: 8pt; color: #4c5563; }
        .paired .categories { width: 53%; float: left; }
        .paired .months { width: 43%; float: right; }
        .tenant-heading { clear: both; padding-top: 10pt; }
        .notes { border-top: .5pt solid #c3cbd7; margin-top: 14pt; padding-top: 7pt; font-size: 8pt; color: #4c5563; }
    </style>
</head>
<body>
    @php($money = fn ($amount) => number_format($amount, 2))
    <header>
        <div class="organization">{{ $dormitoryName }}</div>
        <h1>Financial Report</h1>
        <p><strong>Reporting period</strong> &nbsp; {{ $rangeLabel }}</p>
        <p><strong>Tenant scope</strong> &nbsp; {{ $selectedTenant }}</p>
        <p class="metadata">Generated {{ $generatedAt->format('M j, Y \a\t g:i A') }} PHT &nbsp; | &nbsp; Amounts in Philippine pesos (PHP)</p>
    </header>

    <h2>Billing summary</h2>
    <table class="summary">
        <tbody>
            <tr>
                <td><span>Total billed</span><strong>{{ $money($totalBilled) }}</strong></td>
                <td><span>Collected</span><strong>{{ $money($totalCollected) }}</strong></td>
                <td><span>Outstanding</span><strong>{{ $money($totalOutstanding) }}</strong></td>
                <td><span>Overdue</span><strong>{{ $money($totalOverdue) }}</strong></td>
            </tr>
        </tbody>
    </table>
    <p class="timing">
        {{ $paidOnTime }} paid on time &nbsp; | &nbsp; {{ $paidLate }} paid late
        @if ($unknownPaidDate)
            &nbsp; | &nbsp; {{ $unknownPaidDate }} without a recorded payment date
        @endif
    </p>

    <div @class(['paired' => $months->count() <= 6])>
        <section class="categories">
            <h2>Billing by category</h2>
            <p class="context">Charges grouped by type.</p>
            <table>
                <thead><tr><th scope="col" style="width: 41%">Category</th><th scope="col" class="number">Billed</th><th scope="col" class="number">Collected</th></tr></thead>
                <tbody>
                    @foreach ($incomeCategories as $category)
                        <tr><th scope="row">{{ $category['label'] }}</th><td class="number">{{ $money($category['billed']) }}</td><td class="number">{{ $money($category['collected']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="months">
            <h2>Monthly collections</h2>
            <p class="context">Payments grouped by the month charges were due.</p>
            <table>
                <thead><tr><th scope="col" style="width: 56%">Billing month</th><th scope="col" class="number">Collected</th></tr></thead>
                <tbody>
                    @foreach ($months as $month)
                        <tr><th scope="row">{{ $month->label }}</th><td class="number">{{ $money($month->amount) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    </div>

    <h2 class="tenant-heading">Billing by tenant</h2>
    <p class="context">Charges due within the reporting period.</p>
    <table>
        <thead><tr><th scope="col" style="width: 27%">Tenant</th><th scope="col" style="width: 20%">Current bed</th><th scope="col" class="number">Billed</th><th scope="col" class="number">Collected</th><th scope="col" class="number">Outstanding</th></tr></thead>
        <tbody>
            @forelse ($tenantRows as $row)
                <tr><th scope="row">{{ $row->name }}</th><td>{{ $row->bed }}</td><td class="number">{{ $money($row->billed) }}</td><td class="number">{{ $money($row->paid) }}</td><td class="number">{{ $money($row->balance) }}</td></tr>
            @empty
                <tr><td colspan="5">No charges or payments matched the selected period.</td></tr>
            @endforelse
        </tbody>
        <tfoot><tr><th scope="row" colspan="2">Total</th><td class="number">{{ $money($totalBilled) }}</td><td class="number">{{ $money($totalCollected) }}</td><td class="number">{{ $money($totalOutstanding) }}</td></tr></tfoot>
    </table>

    <h2>Current unpaid balances</h2>
    <p class="context">As of {{ today()->format('M j, Y') }}. Includes unpaid charges outside the reporting period.</p>
    <table>
        <thead><tr><th scope="col" style="width: 32%">Tenant</th><th scope="col" style="width: 25%">Current bed</th><th scope="col" class="number">Unpaid months</th><th scope="col" class="number">Amount owed</th></tr></thead>
        <tbody>
            @forelse ($balanceRows as $row)
                <tr><th scope="row">{{ $row->name }}</th><td>{{ $row->bed }}</td><td class="number">{{ $row->unpaid_months }}</td><td class="number">{{ $money($row->balance) }}</td></tr>
            @empty
                <tr><td colspan="4">No tenants have unpaid balances.</td></tr>
            @endforelse
        </tbody>
        <tfoot><tr><th scope="row" colspan="3">Total across {{ $unpaidTenantCount }} {{ Str::plural('tenant', $unpaidTenantCount) }}</th><td class="number">{{ $money($currentOutstanding) }}</td></tr></tfoot>
    </table>

    <p class="notes">Applied advance payments count toward rent paid for the billing period. Overdue balances are unpaid charges due before the report date.</p>
</body>
</html>
