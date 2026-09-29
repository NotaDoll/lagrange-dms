@extends('layouts.proprietor')

@section('title', 'Financial Reports')

@section('content')
    @php
        use Carbon\Carbon;

        $today = Carbon::today();
        $presets = [
            'month' => ['label' => 'This month',   'from' => $today->copy()->startOfMonth(), 'to' => $today],
            '30d'   => ['label' => 'Last 30 days', 'from' => $today->copy()->subDays(29),    'to' => $today],
            'ytd'   => ['label' => 'Year to date', 'from' => $today->copy()->startOfYear(),  'to' => $today],
        ];

        $isDefault = blank($dateFrom) && blank($dateTo);
        $activePreset = null;
        foreach ($presets as $key => $p) {
            if ($dateFrom === $p['from']->toDateString() && $dateTo === $p['to']->toDateString()) {
                $activePreset = $key;
            }
        }

        $rangeLabel = $isDefault
            ? $today->format('F Y')
            : trim(($dateFrom ? Carbon::parse($dateFrom)->format('M j, Y') : 'Beginning')
                . ' – '
                . ($dateTo ? Carbon::parse($dateTo)->format('M j, Y') : 'Today'));

        $statuses = [
            'pending' => ['label' => 'Pending', 'color' => '#b7791f'],
            'paid'    => ['label' => 'Paid',    'color' => '#c2255c'],
            'overdue' => ['label' => 'Overdue', 'color' => '#8c1d2f'],
        ];

        $grandCount  = 0;
        $grandAmount = 0;
        foreach ($statuses as $s => $_) {
            $grandCount  += (int) ($paymentBreakdown->get($s)?->payment_count ?? 0);
            $grandAmount += (float) ($paymentBreakdown->get($s)?->total_amount ?? 0);
        }

        $chartData = [
            'labels'  => collect($statuses)->pluck('label')->values(),
            'amounts' => collect($statuses)->keys()->map(fn ($s) => (float) ($paymentBreakdown->get($s)?->total_amount ?? 0))->values(),
            'colors'  => collect($statuses)->pluck('color')->values(),
        ];
    @endphp

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        .fr {
            font-family: 'Poppins', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            --fr-ink: #3b1f2e;
            --fr-ink-2: #6a4a5a;
            --fr-line: #f0dbe5;
            --fr-surface: #ffffff;
            --fr-canvas: #fdf4f8;

            --fr-collected: #c2255c;  --fr-collected-bg: #fde8f0;
            --fr-pending:   #b7791f;  --fr-pending-bg:   #fbf0dc;
            --fr-overdue:   #8c1d2f;  --fr-overdue-bg:   #f8e3e6;
            --fr-occupancy: #86308f;  --fr-occupancy-bg: #f4e6f6;

            color: var(--fr-ink);
        }
        .fr button, .fr input, .fr .btn, .fr .form-control { font-family: inherit; }
        .fr .num { font-variant-numeric: tabular-nums; }

        /* Header */
        .fr-title { font-size: 1.5rem; font-weight: 600; }
        .fr-sub   { color: var(--fr-ink-2); font-size: .9rem; }

        /* Filter bar + chips */
        .fr-filters { background: var(--fr-surface); border: 1px solid var(--fr-line); border-radius: .75rem; padding: 1rem 1.25rem; }
        .fr-chip {
            display: inline-flex; align-items: center;
            padding: .3rem .8rem; border-radius: 999px;
            border: 1px solid var(--fr-line); background: var(--fr-surface);
            color: var(--fr-ink-2); font-size: .85rem; text-decoration: none;
            transition: background-color .15s, border-color .15s, color .15s;
        }
        .fr-chip:hover { background: var(--fr-canvas); border-color: #e5b5cc; color: var(--fr-ink); }
        .fr-chip.is-active { background: var(--fr-collected); border-color: var(--fr-collected); color: #fff; }
        .fr .form-label { font-size: .8rem; color: var(--fr-ink-2); margin-bottom: .25rem; }

        /* KPI cards */
        .fr-kpi {
            height: 100%; padding: 1.1rem 1.25rem;
            background: var(--fr-surface); border: 1px solid var(--fr-line); border-radius: .75rem;
            display: flex; gap: .9rem; align-items: flex-start;
            transition: border-color .15s, box-shadow .15s;
        }
        .fr-kpi:hover { border-color: #e5b5cc; box-shadow: 0 1px 6px rgba(194, 37, 92, .10); }
        .fr-kpi--collected { background: var(--fr-collected-bg); border-color: #f6c9db; }
        .fr-icon {
            flex: 0 0 auto; width: 2.5rem; height: 2.5rem; border-radius: .6rem;
            display: grid; place-items: center;
        }
        .fr-icon svg { width: 1.25rem; height: 1.25rem; }
        .fr-icon--collected { background: #fff; color: var(--fr-collected); }
        .fr-icon--pending   { background: var(--fr-pending-bg);   color: var(--fr-pending); }
        .fr-icon--overdue   { background: var(--fr-overdue-bg);   color: var(--fr-overdue); }
        .fr-icon--occupancy { background: var(--fr-occupancy-bg); color: var(--fr-occupancy); }
        .fr-kpi-label { font-size: .85rem; color: var(--fr-ink-2); }
        .fr-kpi-value { font-size: 1.45rem; font-weight: 600; line-height: 1.3; margin: .1rem 0 .15rem; }
        .fr-kpi-value small { font-size: .9rem; font-weight: 500; color: var(--fr-ink-2); margin-right: .15rem; }
        .fr-kpi-note { font-size: .8rem; color: var(--fr-ink-2); }
        .fr-meter { height: .375rem; background: #ecd3f0; border-radius: 999px; overflow: hidden; margin: .4rem 0 .3rem; }
        .fr-meter > span { display: block; height: 100%; background: var(--fr-occupancy); border-radius: inherit; }

        /* Panels */
        .fr-panel { background: var(--fr-surface); border: 1px solid var(--fr-line); border-radius: .75rem; height: 100%; }
        .fr-panel-head { padding: .9rem 1.25rem; border-bottom: 1px solid var(--fr-line); font-weight: 600; }
        .fr-panel-body { padding: 1.25rem; }

        /* Chart */
        .fr-chart { position: relative; height: 240px; }
        .fr-chart-center {
            position: absolute; inset: 0; display: grid; place-content: center;
            text-align: center; pointer-events: none;
        }
        .fr-chart-center .v { font-size: 1.1rem; font-weight: 600; }
        .fr-chart-center .l { font-size: .8rem; color: var(--fr-ink-2); }
        .fr-empty { color: var(--fr-ink-2); text-align: center; padding: 3rem 1rem; }

        /* Table */
        .fr-table { margin: 0; }
        .fr-table thead th {
            font-size: .8rem; font-weight: 600; color: var(--fr-ink-2);
            background: var(--fr-canvas); border-bottom: 1px solid var(--fr-line); padding: .7rem 1rem;
        }
        .fr-table tbody td { padding: .95rem 1rem; border-color: var(--fr-line); }
        .fr-table tbody tr { transition: background-color .12s; }
        .fr-table tbody tr:hover { background: #fef9fb; }
        .fr-table tfoot td { padding: .85rem 1rem; font-weight: 600; background: var(--fr-canvas); border-top: 1px solid var(--fr-line); }
        .fr-dot { display: inline-block; width: .6rem; height: .6rem; border-radius: 50%; margin-right: .55rem; }
        .fr-share { display: flex; align-items: center; gap: .6rem; min-width: 7rem; }
        .fr-share .bar { flex: 1; height: .35rem; background: #f7e6ee; border-radius: 999px; overflow: hidden; }
        .fr-share .bar span { display: block; height: 100%; border-radius: inherit; }
        .fr-share .pct { font-size: .8rem; color: var(--fr-ink-2); width: 2.6rem; text-align: right; }

        /* Pink theme for Bootstrap buttons and form focus */
        .fr .btn-primary {
            --bs-btn-bg: var(--fr-collected); --bs-btn-border-color: var(--fr-collected);
            --bs-btn-hover-bg: #a71d4e; --bs-btn-hover-border-color: #a71d4e;
            --bs-btn-active-bg: #8f1943; --bs-btn-active-border-color: #8f1943;
            --bs-btn-focus-shadow-rgb: 194, 37, 92;
        }
        .fr .btn-outline-secondary {
            --bs-btn-color: var(--fr-ink-2); --bs-btn-border-color: #e5b5cc;
            --bs-btn-hover-bg: var(--fr-collected-bg); --bs-btn-hover-color: var(--fr-ink); --bs-btn-hover-border-color: #e5b5cc;
            --bs-btn-active-bg: #f9d3e2; --bs-btn-active-color: var(--fr-ink); --bs-btn-active-border-color: #e5b5cc;
        }
        .fr .form-control:focus { border-color: #e58aae; box-shadow: 0 0 0 .2rem rgba(194, 37, 92, .15); }

        /* Accessibility */
        .fr a:focus-visible, .fr button:focus-visible, .fr input:focus-visible {
            outline: 2px solid var(--fr-collected); outline-offset: 2px;
        }
        @media (prefers-reduced-motion: reduce) { .fr * { transition: none !important; } }

        /* Print header hidden on screen */
        .fr-print-head { display: none; }

        @media print {
            @page { size: A4 portrait; margin: 14mm; }

            /* Strip app chrome (adjust selectors to your layout) */
            nav, aside, header.navbar, .sidebar, .navbar, footer, .no-print { display: none !important; }
            body, main, .container, .container-fluid { background: #fff !important; padding: 0 !important; margin: 0 !important; max-width: none !important; }
            .fr, .fr * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

            .fr-print-head { display: block; margin-bottom: 1rem; padding-bottom: .6rem; border-bottom: 2px solid var(--fr-collected); }
            .fr-print-head h1 { font-size: 16pt; margin: 0; }
            .fr-print-head p { margin: .15rem 0 0; font-size: 9pt; color: var(--fr-ink-2); }

            .fr-kpi, .fr-panel { box-shadow: none !important; break-inside: avoid; page-break-inside: avoid; }
            .fr-kpi { padding: .7rem .8rem; }
            .fr-kpi-value { font-size: 14pt; }
            .fr-icon { display: none; }
            .fr-chart { height: 200px; }
            .fr-table tr { break-inside: avoid; }
            .fr .col-xl-3 { flex: 0 0 50%; max-width: 50%; }
            .fr .col-lg-5, .fr .col-lg-7 { flex: 0 0 100%; max-width: 100%; }
            .fr .col-lg-5 { margin-bottom: .75rem; }
        }
    </style>

    <div class="fr">
        {{-- Print-only header --}}
        <div class="fr-print-head">
            <h1>Financial Report</h1>
            <p>Period: {{ $rangeLabel }} &nbsp;|&nbsp; Generated {{ now()->format('M j, Y g:i A') }}</p>
        </div>

        {{-- Page header --}}
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3 no-print">
            <div>
                <h1 class="fr-title mb-1">Income reports</h1>
                <div class="fr-sub">Showing {{ $rangeLabel }}</div>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">Print or save as PDF</button>
        </div>

        {{-- Filters --}}
        <div class="fr-filters mb-3 no-print">
            <div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Quick date ranges">
                <a href="{{ route('proprietor.reports.index') }}"
                   class="fr-chip {{ $isDefault ? 'is-active' : '' }}"
                   @if($isDefault) aria-current="true" @endif>Default</a>
                @foreach ($presets as $key => $p)
                    <a href="{{ route('proprietor.reports.index', ['date_from' => $p['from']->toDateString(), 'date_to' => $p['to']->toDateString()]) }}"
                       class="fr-chip {{ $activePreset === $key ? 'is-active' : '' }}"
                       @if($activePreset === $key) aria-current="true" @endif>{{ $p['label'] }}</a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('proprietor.reports.index') }}" class="row g-3 align-items-end">
                <div class="col-sm-6 col-md-4">
                    <label for="date_from" class="form-label">From</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="{{ $dateFrom }}">
                </div>
                <div class="col-sm-6 col-md-4">
                    <label for="date_to" class="form-label">To</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="{{ $dateTo }}">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply filter</button>
                    <a href="{{ route('proprietor.reports.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>

        {{-- KPIs --}}
        <div class="row g-3 mb-3">
            <div class="col-md-6 col-xl-3">
                <div class="fr-kpi fr-kpi--collected">
                    <div class="fr-icon fr-icon--collected" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 9.5v.01M18 14.5v.01"/></svg>
                    </div>
                    <div>
                        <div class="fr-kpi-label">Collected {{ $isDefault ? 'this month' : 'in range' }}</div>
                        <div class="fr-kpi-value num"><small>PHP</small>{{ number_format($totalCollected, 2) }}</div>
                        <div class="fr-kpi-note">{{ (int) ($paymentBreakdown->get('paid')?->payment_count ?? 0) }} paid payments</div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="fr-kpi">
                    <div class="fr-icon fr-icon--pending" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    </div>
                    <div>
                        <div class="fr-kpi-label">Pending</div>
                        <div class="fr-kpi-value num"><small>PHP</small>{{ number_format($totalPending, 2) }}</div>
                        <div class="fr-kpi-note">{{ (int) ($paymentBreakdown->get('pending')?->payment_count ?? 0) }} awaiting payment</div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="fr-kpi">
                    <div class="fr-icon fr-icon--overdue" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.5 2.8 19.5h18.4L12 3.5Z"/><path d="M12 10v4.5M12 17.5v.01"/></svg>
                    </div>
                    <div>
                        <div class="fr-kpi-label">Overdue</div>
                        <div class="fr-kpi-value num" @if($totalOverdue > 0) style="color: var(--fr-overdue)" @endif><small>PHP</small>{{ number_format($totalOverdue, 2) }}</div>
                        <div class="fr-kpi-note">{{ (int) ($paymentBreakdown->get('overdue')?->payment_count ?? 0) }} past due</div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="fr-kpi">
                    <div class="fr-icon fr-icon--occupancy" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18V7M3 14h18v4M21 14v-2a3 3 0 0 0-3-3h-7v5"/><circle cx="7" cy="11" r="1.5"/></svg>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fr-kpi-label">Occupancy rate</div>
                        <div class="fr-kpi-value num">{{ number_format($occupancyRate, 2) }}%</div>
                        <div class="fr-meter" role="progressbar" aria-valuenow="{{ round($occupancyRate) }}" aria-valuemin="0" aria-valuemax="100" aria-label="Occupancy rate">
                            <span @style(['width: ' . min(100, max(0, $occupancyRate)) . '%'])></span>
                        </div>
                        <div class="fr-kpi-note">{{ $occupiedBeds }} of {{ $totalBeds }} beds occupied</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Chart + breakdown --}}
        <div class="row g-3">
            <div class="col-lg-5">
                <div class="fr-panel">
                    <div class="fr-panel-head">Amount by payment status</div>
                    <div class="fr-panel-body">
                        @if ($grandAmount > 0)
                            <div class="fr-chart">
                                <canvas id="statusChart" data-chart='@json($chartData)' role="img" aria-label="Donut chart of payment amounts by status. The table shows the same figures."></canvas>
                                <div class="fr-chart-center">
                                    <div class="v num">PHP {{ number_format($grandAmount, 2) }}</div>
                                    <div class="l">across {{ $grandCount }} payments</div>
                                </div>
                            </div>
                        @else
                            <div class="fr-empty">No payments in this period. Try a wider date range.</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="fr-panel">
                    <div class="fr-panel-head">Payment breakdown</div>
                    <div class="table-responsive">
                        <table class="table align-middle fr-table">
                            <thead>
                                <tr>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="text-end">Payments</th>
                                    <th scope="col" class="text-end">Total amount</th>
                                    <th scope="col">Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($statuses as $status => $meta)
                                    @php
                                        $row    = $paymentBreakdown->get($status);
                                        $amount = (float) ($row?->total_amount ?? 0);
                                        $share  = $grandAmount > 0 ? ($amount / $grandAmount) * 100 : 0;
                                    @endphp
                                    <tr>
                                        <td><span class="fr-dot" @style(['background: ' . $meta['color']])></span>{{ $meta['label'] }}</td>
                                        <td class="text-end num">{{ $row?->payment_count ?? 0 }}</td>
                                        <td class="text-end num">PHP {{ number_format($amount, 2) }}</td>
                                        <td>
                                            <div class="fr-share">
                                                <div class="bar"><span @style(['width: ' . $share . '%', 'background: ' . $meta['color']])></span></div>
                                                <span class="pct num">{{ number_format($share, 0) }}%</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>Total</td>
                                    <td class="text-end num">{{ $grandCount }}</td>
                                    <td class="text-end num">PHP {{ number_format($grandAmount, 2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($grandAmount > 0)
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
        <script>
            (function () {
                var canvas = document.getElementById('statusChart');
                var data = JSON.parse(canvas.dataset.chart);
                var chart = new Chart(canvas, {
                    type: 'doughnut',
                    data: {
                        labels: data.labels,
                        datasets: [{ data: data.amounts, backgroundColor: data.colors, borderColor: '#fff', borderWidth: 3, hoverOffset: 4 }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        cutout: '68%',
                        animation: { duration: 600 },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                        var pct = total ? (ctx.parsed / total * 100).toFixed(0) : 0;
                                        return ' ' + ctx.label + ': PHP ' + ctx.parsed.toLocaleString(undefined, { minimumFractionDigits: 2 }) + ' (' + pct + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
                // Keep the canvas crisp when printing
                window.addEventListener('beforeprint', function () { chart.resize(); });
                window.addEventListener('afterprint',  function () { chart.resize(); });
            })();
        </script>
    @endif
@endsection
