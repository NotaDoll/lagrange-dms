@extends('layouts.proprietor')

@section('title', 'Financial Report')

@section('content')
    <style>
        .financial-report { color: var(--theme-text-dark); }
        .financial-report h1 { font-size: 1.75rem; font-weight: 700; }
        .financial-report .fr-muted { color: var(--theme-text-muted); font-size: .85rem; }
        .financial-report .fr-panel { border: 1px solid var(--theme-border); border-radius: 16px; background: white; padding: 22px; height: 100%; }
        .financial-report .fr-kpi { font-size: 1.75rem; font-weight: 700; margin: 8px 0; font-variant-numeric: tabular-nums; }
        .financial-report .fr-collected { background: var(--theme-pink-card); }
        .financial-report .fr-accent { color: var(--theme-plum); }
        .financial-report .fr-overdue { color: var(--theme-red); }
        .financial-report .form-label { font-size: .8rem; font-weight: 600; color: var(--theme-text-muted); }
        .financial-report .form-control, .financial-report .form-select { border-color: var(--theme-border); border-radius: 9px; min-height: 42px; }
        .financial-report .form-control:focus, .financial-report .form-select:focus { border-color: var(--theme-plum); box-shadow: 0 0 0 .2rem #5e104915; }
        .financial-report h2 { font-size: 1.05rem; font-weight: 600; }
        .financial-report .fr-chart { display: flex; align-items: stretch; gap: 18px; min-width: 100%; width: max-content; height: 235px; padding: 14px 4px 0; }
        .financial-report .fr-chart-column { flex: 1 0 64px; display: flex; flex-direction: column; text-align: center; }
        .financial-report .fr-bar-track { flex: 1; display: flex; align-items: flex-end; justify-content: center; border-bottom: 1px solid var(--theme-border); padding-top: 25px; }
        .financial-report .fr-bar { background: var(--theme-plum); border-radius: 5px 5px 0 0; width: 80%; max-width: 64px; position: relative; min-height: 2px; }
        .financial-report .fr-bar-value { position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); font-size: .68rem; white-space: nowrap; padding-bottom: 6px; font-weight: 600; }
        .financial-report .fr-chart-label { padding-top: 10px; font-size: .7rem; color: var(--theme-text-muted); }
        .financial-report .fr-table { font-size: .84rem; font-variant-numeric: tabular-nums; }
        .financial-report .fr-table th, .financial-report .fr-table td { padding: 13px 10px; vertical-align: middle; }
        .financial-report .fr-table tfoot { border-top: 2px solid var(--theme-plum); font-weight: 700; }
        .financial-report .fr-print-heading { display: none; }
        @media print {
            @page { size: A4 landscape; margin: 12mm; }
            .sidebar, .proprietor-mobile-navbar, .offcanvas, .fr-no-print { display: none !important; }
            body, .proprietor-main, .proprietor-content { padding: 0 !important; margin: 0 !important; background: white !important; }
            .financial-report { width: 100%; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .financial-report .fr-print-heading { display: block; }
            .financial-report .fr-summary > div { width: 25%; }
            .financial-report .fr-details > div { width: 100%; }
            .financial-report .fr-panel { height: auto; }
            .financial-report .table-responsive { overflow: visible; }
            .financial-report .fr-chart-scroll { overflow: visible !important; }
            .financial-report .fr-chart { width: 100%; gap: 2px; }
            .financial-report .fr-chart-column { flex: 1; min-width: 0; }
            .financial-report .fr-table { min-width: 0; }
            .financial-report tr, .financial-report .fr-summary { break-inside: avoid; }
        }
    </style>

    <div class="financial-report">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-1">Financial Report</h1>
                <p class="fr-muted mb-0">Payment summaries and collectibles by billing period and tenant.</p>
            </div>
            <button type="button" class="btn btn-primary px-4 py-2 fr-no-print" onclick="window.print()"><i class="bi bi-file-earmark-pdf me-2" aria-hidden="true"></i>Export PDF</button>
        </div>
        <p class="fr-print-heading">{{ $rangeLabel }} · {{ $selectedTenant }} · Generated {{ now()->format('M j, Y g:i A') }}</p>

        @if ($errors->any())
            <div class="alert alert-danger fr-no-print" role="alert"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <form method="GET" action="{{ route('proprietor.reports.index') }}" class="fr-panel mb-4 fr-no-print">
            <div class="row g-3 align-items-end">
                <div class="col-sm-6 col-lg-3">
                    <label for="date_from" class="form-label">From</label>
                    <input type="date" id="date_from" name="date_from" value="{{ old('date_from', $dateFrom) }}" class="form-control" required>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="date_to" class="form-label">To</label>
                    <input type="date" id="date_to" name="date_to" value="{{ old('date_to', $dateTo) }}" class="form-control" required>
                </div>
                <div class="col-sm-8 col-lg-4">
                    <label for="tenant_id" class="form-label">Tenant</label>
                    <select id="tenant_id" name="tenant_id" class="form-select">
                        <option value="">All tenants</option>
                        @foreach ($tenants as $tenant)
                            <option value="{{ $tenant->id }}" @selected((string) $tenantId === (string) $tenant->id)>{{ $tenant->user?->name ?? 'Unknown tenant' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-4 col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary py-2 px-3">Apply</button>
                    <a href="{{ route('proprietor.reports.index') }}" class="btn btn-outline-primary py-2">Reset</a>
                </div>
            </div>
        </form>

        <p class="fr-muted fr-no-print mb-3">{{ $rangeLabel }} · {{ $selectedTenant }}</p>
        <section class="fr-panel mb-4" aria-labelledby="outstanding-title">
            <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
                <div>
                    <h2 id="outstanding-title">Current unpaid balances</h2>
                    <p class="fr-muted mb-0">As of {{ today()->format('M j, Y') }} · {{ $selectedTenant }}. Includes earlier unpaid months, regardless of the date range below.</p>
                </div>
                <div class="text-end">
                    <div class="fr-kpi fr-overdue mt-0 mb-1">₱{{ number_format($currentOutstanding, 2) }}</div>
                    <div class="fr-muted">{{ $unpaidTenantCount }} {{ Str::plural('tenant', $unpaidTenantCount) }} with unpaid balances</div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table fr-table mb-0">
                    <caption class="visually-hidden">Tenants with current outstanding balances</caption>
                    <thead><tr><th scope="col">Tenant</th><th scope="col">Current bed</th><th scope="col" class="text-end">Unpaid months</th><th scope="col" class="text-end">Amount owed</th></tr></thead>
                    <tbody>
                        @forelse ($balanceRows as $row)
                            <tr><th scope="row">{{ $row->name }}</th><td>{{ $row->bed }}</td><td class="text-end">{{ $row->unpaid_months }}</td><td class="text-end text-nowrap fr-overdue fw-semibold">₱{{ number_format($row->balance, 2) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="text-center fr-muted py-4">No tenants with unpaid balances as of today.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot><tr><th scope="row" colspan="3">Total outstanding · {{ $unpaidTenantCount }} {{ Str::plural('tenant', $unpaidTenantCount) }}</th><td class="text-end text-nowrap">₱{{ number_format($currentOutstanding, 2) }}</td></tr></tfoot>
                </table>
            </div>
            <p class="fr-muted mt-3 mb-0">Includes accrued rent for completed billing months from room assignments and recorded charges, including utility shares awaiting collection. Payments reduce their own billing month; rent and ledger charges for the same month are reconciled to avoid double counting.</p>
        </section>


        <div class="row g-3 mb-4 fr-summary">
            <div class="col-sm-6 col-xl-3"><div class="fr-panel fr-collected">
                <div class="fr-muted">Collected</div>
                <div class="fr-kpi fr-accent">₱{{ number_format($totalCollected, 2) }}</div>
                <div class="fr-muted">{{ $paid->count() }} paid {{ Str::plural('payment', $paid->count()) }}</div>
            </div></div>
            <div class="col-sm-6 col-xl-3"><div class="fr-panel">
                <div class="fr-muted">Still to collect · selected period</div>
                <div class="fr-kpi">₱{{ number_format($totalOutstanding, 2) }}</div>
                <div class="fr-muted">{{ $tenantRows->where('balance', '>', 0)->count() }} {{ Str::plural('tenant', $tenantRows->where('balance', '>', 0)->count()) }} · of ₱{{ number_format($totalBilled, 2) }} billed</div>
            </div></div>
            <div class="col-sm-6 col-xl-3"><div class="fr-panel">
                <div class="fr-muted">Paid on time</div>
                <div class="fr-kpi">{{ $paidOnTime }} <span class="fs-6 fw-normal">of {{ $paid->count() }}</span></div>
                <div class="fr-muted">{{ $paidLate }} paid late @if ($unknownPaidDate) · {{ $unknownPaidDate }} without payment date @endif</div>
            </div></div>
            <div class="col-sm-6 col-xl-3"><div class="fr-panel">
                <div class="fr-muted">Overdue</div>
                <div class="fr-kpi fr-overdue">₱{{ number_format($totalOverdue, 2) }}</div>
                <div class="fr-muted">{{ $overdue->pluck('tenant_id')->unique()->count() }} {{ Str::plural('tenant', $overdue->pluck('tenant_id')->unique()->count()) }}</div>
            </div></div>
        </div>

        <div class="row g-4 fr-details">
            <div class="col-xl-5"><section class="fr-panel" aria-labelledby="monthly-title">
                <h2 id="monthly-title">Collected per billing month</h2>
                <p class="fr-muted">Paid ledger amounts grouped by the month they were due.</p>
                @if ($totalCollected > 0)
                    <div class="overflow-auto fr-chart-scroll" tabindex="0" aria-label="Monthly collections chart">
                        <div class="fr-chart">
                            @foreach ($months as $month)
                                <div class="fr-chart-column" aria-label="{{ $month->label }}: ₱{{ number_format($month->amount, 2) }}">
                                    <div class="fr-bar-track"><div class="fr-bar" style="height: {{ round($month->amount / $chartMax * 100, 2) }}%"><span class="fr-bar-value">₱{{ number_format($month->amount, 0) }}</span></div></div>
                                    <div class="fr-chart-label">{{ $month->label }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="text-center fr-muted py-5"><i class="bi bi-bar-chart fs-2 d-block mb-2" aria-hidden="true"></i>No collections for this billing period.</div>
                @endif
            </section></div>
            <div class="col-xl-7"><section class="fr-panel" aria-labelledby="tenant-title">
                <h2 id="tenant-title">Billing period by tenant</h2>
                <p class="fr-muted">Amounts due in the selected period · current payment status.</p>
                <div class="table-responsive">
                    <table class="table fr-table mb-2">
                        <caption class="visually-hidden">Tenant billed, paid, and outstanding amounts for {{ $rangeLabel }}</caption>
                        <thead><tr><th scope="col">Tenant</th><th scope="col">Current bed</th><th scope="col" class="text-end">Billed</th><th scope="col" class="text-end">Paid</th><th scope="col" class="text-end">Balance</th></tr></thead>
                        <tbody>
                            @forelse ($tenantRows as $row)
                                <tr><td class="fw-semibold">{{ $row->name }}</td><td>{{ $row->bed }}</td><td class="text-end text-nowrap">₱{{ number_format($row->billed, 2) }}</td><td class="text-end text-nowrap">₱{{ number_format($row->paid, 2) }}</td><td class="text-end text-nowrap {{ $row->balance > 0 ? 'fr-overdue fw-semibold' : '' }}">₱{{ number_format($row->balance, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="text-center fr-muted py-5">No rent charges or payments match these filters.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot><tr><th scope="row" colspan="2">Total</th><td class="text-end text-nowrap">₱{{ number_format($totalBilled, 2) }}</td><td class="text-end text-nowrap">₱{{ number_format($totalCollected, 2) }}</td><td class="text-end text-nowrap">₱{{ number_format($totalOutstanding, 2) }}</td></tr></tfoot>
                    </table>
                </div>
                <p class="fr-muted mt-3 mb-0">Showing {{ $tenantRows->count() }} {{ Str::plural('tenant', $tenantRows->count()) }} with rent charges or payments.</p>
            </section></div>
        </div>
        <p class="fr-muted mt-3 mb-0">Reports include recorded ledger entries (including utility shares) and accrued rent due within this period. Rent is due at month end; the current unfinished month is excluded unless a charge is recorded. Overdue balances are unpaid charges due before today. Export PDF opens the print dialog; choose Save as PDF.</p>
    </div>
@endsection
