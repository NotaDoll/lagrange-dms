@extends('layouts.proprietor')
@section('title', 'Dashboard')
@section('content')
    @include('proprietor.dashboard-styles')
    <div class="pd" id="proprietor-dashboard">
        <header class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div><h1 class="h3 fw-bold mb-1">Dashboard</h1><p class="text-muted mb-0">{{ today()->format('l, F j, Y') }}</p></div>
        </header>
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3"><section class="card h-100"><div class="card-body">
                <h2 class="pd-stat-label">Occupancy</h2><div class="pd-stat-value">{{ $occupiedBeds }} <span>/ {{ $totalBeds }}</span></div>
                <div class="progress pd-progress my-2" role="progressbar" aria-label="Occupied beds" aria-valuenow="{{ $occupancyRate }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: {{ min(100, $occupancyRate) }}%"></div></div>
                <a href="{{ route('proprietor.rooms.index') }}" class="small">{{ $occupancyRate }}% occupied · {{ $availableBeds }} available</a>
            </div></section></div>
            <div class="col-sm-6 col-xl-3"><section class="card h-100"><div class="card-body">
                <h2 class="pd-stat-label">Collected this month</h2><div class="pd-stat-value">₱{{ number_format($collectedThisMonth, 2) }}</div>
                <a href="{{ route('proprietor.reports.index', ['date_from' => $periodFrom, 'date_to' => $periodTo]) }}" class="small">of ₱{{ number_format($expectedThisMonth, 2) }} expected</a><p class="pd-note mb-0 mt-1">For charges due {{ today()->format('F Y') }}.</p>
            </div></section></div>
            <div class="col-sm-6 col-xl-3"><section class="card h-100"><div class="card-body">
                <h2 class="pd-stat-label">Overdue balance</h2><div class="pd-stat-value text-danger">₱{{ number_format($overdueBalance, 2) }}</div>
                <a href="{{ route('proprietor.reports.index', ['date_from' => '1900-01-01', 'date_to' => $periodTo]) }}" class="small">{{ $overdueTenants }} {{ Str::plural('tenant', $overdueTenants) }} with overdue charges</a><p class="pd-note mb-0 mt-1">Unpaid charges due before today.</p>
            </div></section></div>
            <div class="col-sm-6 col-xl-3"><section class="card h-100"><div class="card-body">
                <h2 class="pd-stat-label">Open complaints</h2><div class="pd-stat-value">{{ $openComplaints }}</div>
                <a href="{{ route('proprietor.complaints.index') }}" class="small">{{ $submittedComplaints }} open · {{ $inProgressComplaints }} in progress</a><p class="pd-note mb-0 mt-1">{{ $newComplaintsToday }} of these are new today.</p>
            </div></section></div>
        </div>

        @php
            $highRisk = $riskCounts['high'];
            $assessed = array_sum($riskCounts);
            $dueSoonTotal = (float) $dueSoon->sum('amount');
            $topIssue = $summaryContent['themes'][0] ?? null;
            // Each tile: danger (act now), warning (check soon), info (good to know), or clear (nothing to do).
            $tiles = collect([
                ['key' => 'risk', 'level' => $highRisk > 0 ? 'danger' : 'clear'],
                ['key' => 'complaints', 'level' => $oldComplaints > 0 ? 'warning' : 'clear'],
                ['key' => 'due', 'level' => $dueSoon->isNotEmpty() ? 'info' : 'clear'],
                ['key' => 'ai', 'level' => $latestSummary ? 'info' : 'clear'],
            ])->sortBy(fn ($tile) => array_search($tile['level'], ['danger', 'warning', 'info', 'clear']))->values();
        @endphp
        <section class="mb-4" aria-labelledby="pd-priority">
            <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
                <h2 id="pd-priority" class="h6 fw-semibold mb-0">Priority alerts</h2>
                <span class="pd-note">Most urgent first</span>
            </div>
            <div class="row g-3">
                @foreach ($tiles as $tile)
                    <div class="col-sm-6 col-xl-3">
                        <article class="card h-100 pd-tile pd-tile-{{ $tile['level'] }}"><div class="card-body d-flex flex-column gap-1">
                        @switch($tile['key'])
                            @case('risk')
                                <h3 class="pd-stat-label mb-1"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> High-risk tenants</h3>
                                @if ($highRisk > 0)
                                    <div class="pd-tile-value">{{ $highRisk }}</div>
                                    @if ($assessed > 0)
                                        <div class="pd-riskbar" role="img" aria-label="{{ $riskCounts['high'] }} high, {{ $riskCounts['medium'] }} medium, {{ $riskCounts['low'] }} low risk">
                                            @foreach (['high', 'medium', 'low'] as $level)
                                                @if ($riskCounts[$level] > 0)<span class="pd-risk-{{ $level }}" style="width: {{ round($riskCounts[$level] / $assessed * 100, 1) }}%"></span>@endif
                                            @endforeach
                                        </div>
                                    @endif
                                    <p class="pd-note mb-2">{{ $riskCounts['high'] }} high · {{ $riskCounts['medium'] }} medium · {{ $riskCounts['low'] }} low</p>
                                    <a href="#pd-follow" class="btn btn-sm btn-outline-danger mt-auto align-self-start">Follow up</a>
                                @else
                                    <p class="pd-tile-ok mb-0"><i class="bi bi-check-circle" aria-hidden="true"></i> No high-risk tenants</p>
                                    <p class="pd-note mb-0">{{ $riskCounts['medium'] }} medium · {{ $riskCounts['low'] }} low</p>
                                @endif
                                @break
                            @case('complaints')
                                <h3 class="pd-stat-label mb-1"><i class="bi bi-hourglass-split" aria-hidden="true"></i> Complaints waiting</h3>
                                @if ($oldComplaints > 0)
                                    <div class="pd-tile-value">{{ $oldComplaints }}</div>
                                    <p class="pd-note mb-2">Open more than 7 days · oldest {{ $oldestOpenComplaintDays }} {{ Str::plural('day', $oldestOpenComplaintDays) }}</p>
                                    <a href="{{ route('proprietor.complaints.index') }}" class="btn btn-sm btn-outline-warning mt-auto align-self-start">Review</a>
                                @else
                                    <p class="pd-tile-ok mb-0"><i class="bi bi-check-circle" aria-hidden="true"></i> None waiting over 7 days</p>
                                    <p class="pd-note mb-0">{{ $openComplaints }} open in total</p>
                                @endif
                                @break
                            @case('due')
                                <h3 class="pd-stat-label mb-1"><i class="bi bi-calendar-event" aria-hidden="true"></i> Rent due in 7 days</h3>
                                @if ($dueSoon->isNotEmpty())
                                    <div class="pd-tile-value">{{ $dueSoon->count() }}</div>
                                    <p class="pd-note mb-2">₱{{ number_format($dueSoonTotal, 2) }} · next {{ $dueSoon->first()->tenant->name }}, {{ $dueSoon->first()->due_date->format('M j') }}</p>
                                    <a href="{{ route('proprietor.payments.create', ['tenant_id' => $dueSoon->first()->tenant->id]) }}" class="btn btn-sm btn-outline-primary mt-auto align-self-start">Record payment</a>
                                @else
                                    <p class="pd-tile-ok mb-0"><i class="bi bi-check-circle" aria-hidden="true"></i> Nothing due this week</p>
                                @endif
                                @break
                            @case('ai')
                                <h3 class="pd-stat-label mb-1"><i class="bi bi-stars" aria-hidden="true"></i> Top complaint</h3>
                                @if ($latestSummary)
                                    <div class="pd-tile-topic">{{ $topIssue ? $topIssue['theme'] : Str::limit(ltrim(Str::before(trim($summaryContent['text'] ?? $summaryContent['alert'] ?? ''), "\n"), "-•* \t"), 60) }}</div>
                                    <p class="pd-note mb-2">@if ($topIssue){{ $topIssue['count'] }} {{ Str::plural('report', (int) $topIssue['count']) }} · @endif from the {{ $latestSummary->created_at->diffForHumans() }} summary</p>
                                    <a href="#pd-summary" class="btn btn-sm btn-outline-secondary mt-auto align-self-start">Read summary</a>
                                @else
                                    <p class="pd-tile-ok mb-0">No summary yet</p>
                                    <a href="#pd-summary" class="btn btn-sm btn-outline-secondary mt-auto align-self-start">Generate one</a>
                                @endif
                                @break
                        @endswitch
                        </div></article>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card mb-4" aria-labelledby="pd-trends"><div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 id="pd-trends" class="h6 fw-semibold mb-0">Trends</h2>
                <div class="btn-group btn-group-sm" role="group" aria-label="Trend period">
                    @foreach (\App\Services\ProprietorDashboardService::TREND_MONTHS as $months)
                        <a href="{{ route('proprietor.dashboard', ['trend' => $months]) }}#pd-trends" class="btn {{ $trendMonths === $months ? 'btn-primary' : 'btn-outline-primary' }}" @if ($trendMonths === $months) aria-current="true" @endif>Last {{ $months }} months</a>
                    @endforeach
                </div>
            </div>
            <div class="row g-4">
                <div class="col-lg-6">
                    <h3 class="pd-stat-label mb-1">Collections</h3>
                    <p class="pd-note mb-2">What was due each month, and how much of it has been paid.</p>
                    <div class="pd-chart"><canvas id="pd-collections-chart" role="img" aria-label="Bar chart of amount due and amount collected per month"></canvas></div>
                </div>
                <div class="col-lg-6">
                    <h3 class="pd-stat-label mb-1">Payment risk over time</h3>
                    <p class="pd-note mb-2">Tenants at each risk level at the end of every month, using the current risk rules.</p>
                    <div class="pd-chart"><canvas id="pd-risk-chart" role="img" aria-label="Stacked bar chart of high, medium and low risk tenants per month"></canvas></div>
                </div>
            </div>
            <details class="mt-3">
                <summary class="pd-note">Show the numbers</summary>
                <div class="table-responsive mt-2"><table class="table table-sm align-middle mb-0 small">
                    <thead><tr><th scope="col">Month</th><th scope="col" class="text-end">Due</th><th scope="col" class="text-end">Collected</th><th scope="col" class="text-end">Rate</th><th scope="col" class="text-end">High</th><th scope="col" class="text-end">Medium</th><th scope="col" class="text-end">Low</th></tr></thead>
                    <tbody>
                        @foreach ($collectionsTrend as $i => $row)
                            <tr>
                                <th scope="row" class="fw-normal">{{ $row['label'] }}</th>
                                <td class="text-end">₱{{ number_format($row['expected'], 2) }}</td>
                                <td class="text-end">₱{{ number_format($row['collected'], 2) }}</td>
                                <td class="text-end">{{ $row['expected'] > 0 ? round($row['collected'] / $row['expected'] * 100).'%' : '—' }}</td>
                                <td class="text-end">{{ $riskTrend[$i]['high'] }}</td>
                                <td class="text-end">{{ $riskTrend[$i]['medium'] }}</td>
                                <td class="text-end">{{ $riskTrend[$i]['low'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
            </details>
        </div></section>

        <div class="row g-4">
            <div class="col-xl-8"><section class="card h-100" aria-labelledby="pd-follow"><div class="card-body">
                @php($textable = $followUps->filter(fn ($row) => $row->risk_level === 'high' && filled($row->tenant->guardian_contact_number))->count())
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <h2 id="pd-follow" class="h6 fw-semibold mb-0">Tenants to follow up</h2>
                    <div class="d-flex flex-wrap gap-2">
                        @if ($textable > 0)
                            <button type="button" class="btn btn-primary" id="pd-sms-all" data-preview-url="{{ route('proprietor.dashboard.sms.bulk-preview') }}" data-url="{{ route('proprietor.dashboard.sms.bulk') }}"><i class="bi bi-chat-dots me-1" aria-hidden="true"></i> Text all high-risk guardians ({{ $textable }})</button>
                        @endif
                        <a class="btn btn-outline-primary" href="{{ route('proprietor.settings.edit') }}#risk-thresholds">Risk thresholds</a>
                    </div>
                </div>
                <p class="pd-note">High risk: 2 or more warning signs. Medium: 1. Sorted by most warning signs, then by amount owed.</p>
                @if ($followUps->isNotEmpty())
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3" id="pd-follow-filters">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Show risk level">
                            @foreach (['all' => 'All', 'high' => 'High', 'medium' => 'Medium'] as $value => $label)
                                <button type="button" class="btn {{ $value === 'all' ? 'btn-primary' : 'btn-outline-primary' }}" data-level="{{ $value }}" aria-pressed="{{ $value === 'all' ? 'true' : 'false' }}">
                                    {{ $label }} <span class="badge text-bg-light ms-1">{{ $value === 'all' ? $followUps->count() : $followUps->where('risk_level', $value)->count() }}</span>
                                </button>
                            @endforeach
                        </div>
                        <label class="visually-hidden" for="pd-follow-search">Search tenants to follow up</label>
                        <input type="search" class="form-control form-control-sm pd-follow-search" id="pd-follow-search" placeholder="Search name or room" autocomplete="off">
                    </div>
                @endif
                <div id="pd-sms-message" class="d-none alert" role="status" aria-live="polite" data-refresh-url="{{ route('proprietor.dashboard.sms.refresh') }}"></div>
                <div class="table-responsive"><table class="table align-middle pd-follow-table">
                    <caption class="visually-hidden">Active tenants with High or Medium payment risk</caption>
                    <thead><tr><th scope="col">Tenant</th><th scope="col">Why flagged</th><th scope="col" class="text-end">Owes</th><th scope="col">Last 6 months</th><th scope="col">Action</th></tr></thead>
                    <tbody>
                        @forelse ($followUps as $row)
                            @php($room = $row->tenant->currentAssignment?->bed?->room?->room_number)
                            <tr data-level="{{ $row->risk_level }}" data-search="{{ Str::lower($row->tenant->name.' '.$room) }}">
                                <td>
                                    <div class="fw-semibold">{{ $row->tenant->name }}</div>
                                    <div class="pd-note">{{ $room ? 'Room '.$room : 'No room' }} · <span class="pd-tag pd-{{ $row->risk_level }}">{{ ucfirst($row->risk_level) }}</span></div>
                                </td>
                                <td>
                                    <ul class="list-unstyled d-flex flex-wrap gap-1 mb-0">
                                        @foreach ($row->reasons as $reason)<li class="pd-reason">{{ $reason }}</li>@endforeach
                                    </ul>
                                </td>
                                <td class="text-end text-nowrap {{ $row->owes > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">₱{{ number_format($row->owes, 2) }}</td>
                                <td>
                                    @php($trendLabel = $row->trend->map(fn ($point) => $point['label'].': '.($point['level'] ?? 'not billed'))->implode(', '))
                                    <div class="pd-history" role="img" aria-label="{{ $trendLabel }}" title="{{ $trendLabel }}">
                                        @foreach ($row->trend as $point)
                                            <span class="pd-history-cell pd-history-{{ $point['level'] ?? 'none' }}"><span>{{ $point['label'] }}</span></span>
                                        @endforeach
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap align-items-center gap-1">
                                        @if ($row->risk_level === 'high' && filled($row->tenant->guardian_contact_number))
                                            <div class="d-inline-flex flex-column align-items-start gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-primary text-nowrap pd-send-sms"
                                                        data-url="{{ route('proprietor.dashboard.sms', $row->tenant) }}"
                                                        data-preview-url="{{ route('proprietor.dashboard.sms.preview', $row->tenant) }}"
                                                        data-log-id="{{ $row->sms_log_id }}">
                                                    {{ $row->sms_sent ? 'Send SMS again' : 'SMS guardian' }}
                                                </button>
                                                <small class="text-muted pd-sms-status" data-tenant-id="{{ $row->tenant->id }}" aria-live="polite">{{ match ($row->sms_status) {
                                                    'queued' => 'SMS queued',
                                                    'unknown' => 'SMS unconfirmed',
                                                    'delivered' => 'SMS delivered',
                                                    'sent' => 'SMS sent',
                                                    default => '',
                                                } }}</small>
                                            </div>
                                        @elseif ($row->risk_level === 'high')
                                            <span class="small text-muted">Guardian number missing</span>
                                        @endif
                                        <a class="btn btn-sm btn-link" href="{{ route('proprietor.payments.history', $row->tenant) }}">Ledger</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-check-circle text-success" aria-hidden="true"></i> No tenants need a payment follow-up right now.</td></tr>
                        @endforelse
                        @if ($followUps->isNotEmpty())
                            <tr class="d-none" id="pd-follow-none"><td colspan="5" class="text-center text-muted py-4">No tenants match this filter.</td></tr>
                        @endif
                    </tbody>
                </table></div>
                <p class="pd-note mb-0">
                    <span class="pd-history-cell pd-history-high pd-history-key"></span> High
                    <span class="pd-history-cell pd-history-medium pd-history-key ms-2"></span> Medium
                    <span class="pd-history-cell pd-history-low pd-history-key ms-2"></span> Low
                    <span class="pd-history-cell pd-history-none pd-history-key ms-2"></span> Not billed yet
                    · Payment reminders go to the guardian's mobile number.
                </p>
            </div></section></div>

            <div class="col-xl-4"><section class="card h-100" aria-labelledby="pd-due"><div class="card-body">
                <h2 id="pd-due" class="h6 fw-semibold mb-1">Rent due in the next 7 days</h2>
                <p class="pd-note">From each tenant's move-in day. Only what's still unpaid.</p>
                @if ($dueSoon->isEmpty())
                    <p class="text-muted mb-0"><i class="bi bi-check-circle text-success" aria-hidden="true"></i> Nothing is due this week.</p>
                @else
                    <ul class="list-unstyled mb-0 pd-due-list">
                        @foreach ($dueSoon->take(8) as $due)
                            @php($daysAway = (int) \Illuminate\Support\Carbon::parse(\App\Models\Tenant::localDate())->diffInDays($due->due_date))
                            <li class="d-flex justify-content-between align-items-center gap-2 py-2">
                                <div class="min-w-0">
                                    <div class="fw-semibold text-truncate">{{ $due->tenant->name }}</div>
                                    <div class="pd-note">{{ $due->due_date->format('D, M j') }} · {{ $daysAway === 0 ? 'today' : ($daysAway === 1 ? 'tomorrow' : 'in '.$daysAway.' days') }} · ₱{{ number_format($due->amount, 2) }}</div>
                                </div>
                                <a class="btn btn-sm btn-outline-primary text-nowrap" href="{{ route('proprietor.payments.create', ['tenant_id' => $due->tenant->id]) }}">Record</a>
                            </li>
                        @endforeach
                    </ul>
                    @if ($dueSoon->count() > 8)
                        <a class="small d-inline-block mt-2" href="{{ route('proprietor.payments.index') }}">And {{ $dueSoon->count() - 8 }} more in the Payment Ledger</a>
                    @endif
                    <p class="pd-note mt-2 mb-0">₱{{ number_format($dueSoon->sum('amount'), 2) }} due in total.</p>
                @endif
            </div></section></div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-xl-5">

                @php($complaintTotal = collect($complaintsByCategory)->sum(fn ($row) => $row['open'] + $row['resolved']))
                <section class="card h-100" aria-labelledby="pd-categories"><div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-1">
                        <h2 id="pd-categories" class="h6 fw-semibold mb-0">Complaints by category</h2>
                        <a class="small" href="{{ route('proprietor.complaints.index') }}">All complaints</a>
                    </div>
                    <p class="pd-note mb-2">Filed in the last {{ $trendMonths }} months ({{ $complaintTotal }} in total). Matches the Trends period.</p>
                    @if ($complaintTotal === 0)
                        <p class="text-muted mb-0"><i class="bi bi-check-circle text-success" aria-hidden="true"></i> No complaints in this period.</p>
                    @else
                        <div class="pd-chart pd-chart-short"><canvas id="pd-categories-chart" role="img" aria-label="Bar chart of open and resolved complaints per category"></canvas></div>
                        <ul class="visually-hidden">
                            @foreach ($complaintsByCategory as $row)<li>{{ $row['label'] }}: {{ $row['open'] }} open, {{ $row['resolved'] }} resolved</li>@endforeach
                        </ul>
                    @endif
                </div></section>

            </div>
            <div class="col-xl-7">
                {{-- REDESIGNED COMPLAINT SUMMARY SECTION --}}
                <section class="card h-100" aria-labelledby="pd-summary">
                    <div class="card-body p-4">

                        {{-- Header --}}
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 border-bottom pb-2">
                            <h2 id="pd-summary" class="h6 fw-bold mb-0">Complaint Summary</h2>
                            <div class="d-flex align-items-center gap-2">
                                @if ($latestSummary)
                                    <span class="badge bg-light text-dark border" style="font-size: 0.65rem;">AI-generated</span>
                                @endif
                                {{-- Subtle regenerate icon next to title --}}
                                <button type="button" class="btn btn-sm btn-light border px-2 py-1" id="pd-generate-summary" data-url="{{ route('proprietor.dashboard.summary') }}" title="Regenerate Summary">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-arrow-clockwise" viewBox="0 0 16 16">
                                        <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2z"/>
                                        <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        @if ($latestSummary)
                            <p class="text-muted mb-3" style="font-size: 0.7rem;">
                                Generated {{ $latestSummary->created_at->diffForHumans() }}
                                @if ($summaryContent['total'] !== null) · {{ $summaryContent['total'] }} open complaints at generation @endif
                            </p>

                            {{-- Dynamic Content Container --}}
                            <div id="pd-summary-content">
                                {{-- Left: Priority Action --}}
                                @if (isset($summaryContent['action']) && $summaryContent['action'])
                                    <div class="p-3 rounded mb-3" style="background-color: #fff5f5; border: 1px solid #fed7d7;">
                                        <h3 class="h6 fw-bold text-danger mb-1 d-flex align-items-center gap-2" style="font-size: 0.85rem;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-exclamation-triangle-fill" viewBox="0 0 16 16">
                                                <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                                            </svg>
                                            Priority Action
                                        </h3>
                                        <p class="mb-0 small text-dark">{{ $summaryContent['action'] }}</p>
                                    </div>
                                @endif

                                {{-- Themes / Issues --}}
                                @if (isset($summaryContent['themes']) && $summaryContent['themes'])
                                    <ul class="list-unstyled mb-0 d-flex flex-column gap-2">
                                        @foreach ($summaryContent['themes'] as $theme)
                                            <li class="d-flex align-items-start gap-2">
                                                <div class="mt-1 text-primary" style="font-size: 1.2rem; line-height: 1;">&bull;</div>
                                                <div>
                                                    <span class="text-dark fw-medium small">{{ $theme['theme'] }}</span>
                                                    <span class="badge bg-secondary ms-1 rounded-pill" style="font-size: 0.6rem;">{{ $theme['count'] }}</span>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    {{-- Fallback for raw text, which will be formatted by JS on load --}}
                                    <div class="text-muted small raw-summary-text" data-raw-text="{{ $summaryContent['text'] ?? '' }}" style="white-space: pre-line; max-height: 200px; overflow-y: auto;">
                                        {{ $summaryContent['text'] ?? '' }}
                                    </div>
                                @endif
                            </div>

                            <div class="mt-3 pt-2 border-top">
                                <p class="text-muted mb-0" style="font-size: 0.65rem;">
                                    <em>AI may contain inaccuracies. Review before taking action.</em>
                                </p>
                            </div>
                        @else
                            <div class="text-center py-5">
                                <p class="text-muted small mb-0">No complaint summary generated yet.</p>
                            </div>
                        @endif

                        <div id="pd-summary-message" class="d-none alert mt-3 mb-0" role="status" aria-live="polite"></div>
                    </div>
                </section>
                {{-- END OF REDESIGNED COMPLAINT SUMMARY --}}

            </div>
        </div>
    </div>
    {{-- Review and edit a guardian reminder before it is sent. --}}
    <div class="modal fade" id="pd-sms-modal" tabindex="-1" aria-labelledby="pd-sms-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="pd-sms-modal-title">Text the guardian</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small mb-2" id="pd-sms-to">Loading…</p>
                <label for="pd-sms-text" class="form-label">Message</label>
                <textarea class="form-control" id="pd-sms-text" rows="6" maxlength="{{ \App\Services\GuardianSmsMessage::MAX_LENGTH }}"></textarea>
                <div class="small text-muted mt-1 pd-sms-count" data-for="pd-sms-text" aria-live="polite"></div>
                <div class="alert alert-warning small mt-2 mb-0 d-none" id="pd-sms-already">This guardian was already texted today. Sending again will reach them twice.</div>
                <div class="alert alert-danger small mt-2 mb-0 d-none" id="pd-sms-error" role="alert"></div>
            </div>
            <div class="modal-footer justify-content-between">
                <a class="small" href="{{ route('proprietor.settings.edit') }}#guardian-sms">Change the default message</a>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="pd-sms-send">Send SMS</button>
                </div>
            </div>
        </div></div>
    </div>

    {{-- Send a personalized reminder to every high-risk tenant's guardian. --}}
    <div class="modal fade" id="pd-sms-all-modal" tabindex="-1" aria-labelledby="pd-sms-all-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="pd-sms-all-title">Text all high-risk guardians</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Each message is filled in for that tenant. Edit any of them, or untick a guardian to leave them out. Guardians already texted today are unticked.</p>
                <div id="pd-sms-all-list"><p class="text-muted">Loading…</p></div>
                <div class="alert small mt-3 mb-0 d-none" id="pd-sms-all-result" role="status"></div>
            </div>
            <div class="modal-footer justify-content-between">
                <a class="small" href="{{ route('proprietor.settings.edit') }}#guardian-sms">Change the default message</a>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="pd-sms-all-send" disabled>Send</button>
                </div>
            </div>
        </div></div>
    </div>

    @include('proprietor.dashboard-script')

    {{-- ADDED JAVASCRIPT FOR IMMEDIATE FORMATTING ON LOAD --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Function to parse raw text into clean UI
            window.formatRawSummary = function(container) {
                const rawTextEl = container.querySelector('.raw-summary-text');
                if (!rawTextEl) return;

                const rawText = rawTextEl.getAttribute('data-raw-text');
                if (!rawText) return;

                const lines = rawText.split('\n');
                let issues = [];
                let criticalAction = '';
                let footerStats = '';

                lines.forEach(line => {
                    let cleanLine = line.trim();
                    if (!cleanLine) return;

                    if (cleanLine.toLowerCase().startsWith('most urgent issue:')) {
                        criticalAction = cleanLine.replace(/most urgent issue:/i, '').trim();
                    }
                    else if (cleanLine.toLowerCase().startsWith('based on') || cleanLine.toLowerCase().startsWith('ai-generated')) {
                        footerStats += cleanLine + ' ';
                    }
                    else if (cleanLine.startsWith('-') || cleanLine.startsWith('•')) {
                        cleanLine = cleanLine.replace(/^[-•]\s*/, '');
                        let countMatch = cleanLine.match(/–\s*(\d+)\s*report/i);
                        let count = countMatch ? countMatch[1] : null;
                        let mainText = cleanLine.replace(/\(complaints?[^)]+\)/ig, '').replace(/–\s*\d+\s*report/i, '').trim();

                        issues.push({ text: mainText, count: count });
                    }
                });

                let html = '';

                if (criticalAction) {
                    html += `
                    <div class="p-3 rounded mb-3" style="background-color: #fff5f5; border: 1px solid #fed7d7;">
                        <h3 class="h6 fw-bold text-danger mb-1 d-flex align-items-center gap-2" style="font-size: 0.85rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-exclamation-triangle-fill" viewBox="0 0 16 16">
                                <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                            </svg>
                            Priority Action
                        </h3>
                        <p class="mb-0 small text-dark">${criticalAction}</p>
                    </div>`;
                }

                if (issues.length > 0) {
                    html += `<ul class="list-unstyled mb-0 d-flex flex-column gap-2">`;
                    issues.forEach(issue => {
                        let badge = issue.count ? `<span class="badge bg-secondary ms-1 rounded-pill" style="font-size: 0.6rem;">${issue.count}</span>` : '';
                        html += `
                            <li class="d-flex align-items-start gap-2">
                                <div class="mt-1 text-primary" style="font-size: 1.2rem; line-height: 1;">&bull;</div>
                                <div>
                                    <span class="text-dark fw-medium small">${issue.text}</span>
                                    ${badge}
                                </div>
                            </li>`;
                    });
                    html += `</ul>`;
                }

                rawTextEl.outerHTML = html;

                if (footerStats) {
                    container.insertAdjacentHTML('beforeend', `<p class="text-muted mt-3 mb-0" style="font-size: 0.65rem;"><em>${footerStats}</em></p>`);
                }
            };

            // 1. Run formatting on initial page load
            const summaryContainer = document.getElementById('pd-summary-content');
            if (summaryContainer) {
                formatRawSummary(summaryContainer);
            }
        });
    </script>

    {{-- Trend charts. Without internet the library won't load; the "Show the numbers" table still has everything. --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof Chart === 'undefined') return;
            const css = getComputedStyle(document.documentElement);
            const plum = css.getPropertyValue('--theme-plum').trim() || '#7a1f5c';
            const muted = css.getPropertyValue('--theme-text-muted').trim() || '#6c757d';
            const grid = css.getPropertyValue('--theme-border').trim() || '#e9e2e7';
            const peso = (n) => '₱' + Number(n).toLocaleString('en-PH', { maximumFractionDigits: 0 });
            // "Nov 2026" -> "Nov"; keep the year on the first bar and on January so the year is never ambiguous.
            const shortLabels = (rows) => rows.map((m, i) => (i === 0 || m.label.startsWith('Jan')) ? m.label : m.label.split(' ')[0]);
            const legend = { position: 'bottom', labels: { boxWidth: 12, boxHeight: 12, padding: 10 } };
            Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
            Chart.defaults.color = muted;
            const axes = (stacked, money) => ({
                x: { stacked, grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true } },
                y: { stacked, beginAtZero: true, grid: { color: grid }, ticks: { precision: 0, callback: money ? (v) => peso(v) : undefined } },
            });

            const collections = @json($collectionsTrend);
            new Chart(document.getElementById('pd-collections-chart'), {
                type: 'bar',
                data: {
                    labels: shortLabels(collections),
                    datasets: [
                        { label: 'Due', data: collections.map((m) => m.expected), backgroundColor: grid, borderRadius: 4 },
                        { label: 'Collected', data: collections.map((m) => m.collected), backgroundColor: plum, borderRadius: 4 },
                    ],
                },
                options: {
                    maintainAspectRatio: false,
                    scales: axes(false, true),
                    plugins: {
                        legend,
                        tooltip: { callbacks: {
                            title: (items) => collections[items[0].dataIndex].label,
                            label: (c) => `${c.dataset.label}: ${peso(c.raw)}`,
                            afterBody: (items) => {
                                const m = collections[items[0].dataIndex];
                                return m.expected > 0 ? `Collected ${Math.round(m.collected / m.expected * 100)}% of what was due` : '';
                            },
                        } },
                    },
                },
            });

            const categories = @json($complaintsByCategory);
            const categoryCanvas = document.getElementById('pd-categories-chart');
            if (categoryCanvas) {
                new Chart(categoryCanvas, {
                    type: 'bar',
                    data: {
                        labels: categories.map((c) => c.label),
                        datasets: [
                            { label: 'Open', data: categories.map((c) => c.open), backgroundColor: '#f0a020', borderRadius: 3 },
                            { label: 'Resolved', data: categories.map((c) => c.resolved), backgroundColor: grid, borderRadius: 3 },
                        ],
                    },
                    options: {
                        indexAxis: 'y',
                        maintainAspectRatio: false,
                        scales: {
                            x: { stacked: true, beginAtZero: true, grid: { color: grid }, ticks: { precision: 0 } },
                            y: { stacked: true, grid: { display: false } },
                        },
                        plugins: {
                            legend,
                            tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${c.raw} ${c.raw === 1 ? 'complaint' : 'complaints'}` } },
                        },
                    },
                });
            }

            const risk = @json($riskTrend);
            new Chart(document.getElementById('pd-risk-chart'), {
                type: 'bar',
                data: {
                    labels: shortLabels(risk),
                    datasets: [
                        { label: 'High', data: risk.map((m) => m.high), backgroundColor: '#d32f2f' },
                        { label: 'Medium', data: risk.map((m) => m.medium), backgroundColor: '#f0a020' },
                        { label: 'Low', data: risk.map((m) => m.low), backgroundColor: '#7cb342' },
                    ],
                },
                options: {
                    maintainAspectRatio: false,
                    scales: axes(true, false),
                    plugins: {
                        legend,
                        tooltip: { callbacks: { title: (items) => risk[items[0].dataIndex].label, label: (c) => `${c.dataset.label} risk: ${c.raw} ${c.raw === 1 ? 'tenant' : 'tenants'}` } },
                    },
                },
            });
        });
    </script>
@endsection
