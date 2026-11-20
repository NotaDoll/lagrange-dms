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
                <a href="{{ route('proprietor.complaints.index') }}" class="small">{{ $urgentComplaints->count() }} urgent · {{ $oldComplaints }} waiting 7+ days</a><p class="pd-note mb-0 mt-1">{{ $newComplaintsToday }} new today.</p>
            </div></section></div>
        </div>

        @php
            $highRisk = $riskCounts['high'];
            $assessed = array_sum($riskCounts);
            $dueSoonTotal = (float) $dueSoon->sum('amount');
            $topIssue = $summaryContent['themes'][0] ?? null;
            $attentionCount = count($attentionComplaintIds);
            // Each tile: danger (act now), warning (check soon), info (good to know), or clear (nothing to do).
            $tiles = collect([
                ['key' => 'risk', 'level' => $highRisk > 0 ? 'danger' : 'clear'],
                ['key' => 'complaints', 'level' => $urgentComplaints->isNotEmpty() ? 'danger' : ($oldComplaints > 0 ? 'warning' : 'clear')],
                ['key' => 'due', 'level' => $dueSoon->isNotEmpty() ? 'info' : 'clear'],
            ])->sortBy(fn ($tile) => array_search($tile['level'], ['danger', 'warning', 'info', 'clear']))->values();
        @endphp
        <section class="mb-4" aria-labelledby="pd-priority">
            <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
                <h2 id="pd-priority" class="h6 fw-semibold mb-0">Priority alerts</h2>
                <span class="pd-note">Most urgent first</span>
            </div>
            <div class="row g-3">
                @foreach ($tiles as $tile)
                    <div class="col-sm-6 col-xl-4">
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
                                    <a href="#pd-follow" class="btn btn-sm btn-outline-danger mt-auto align-self-start"><i class="bi bi-people" aria-hidden="true"></i> Follow up</a>
                                @else
                                    <p class="pd-tile-ok mb-0"><i class="bi bi-check-circle" aria-hidden="true"></i> No high-risk tenants</p>
                                    <p class="pd-note mb-0">{{ $riskCounts['medium'] }} medium · {{ $riskCounts['low'] }} low</p>
                                @endif
                                @break
                            @case('complaints')
                                <h3 class="pd-stat-label mb-1"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i> Urgent complaints</h3>
                                @if ($attentionCount > 0)
                                    <div class="pd-tile-value">{{ $attentionCount }}</div>
                                    @if ($urgentComplaints->isNotEmpty())
                                        @php($firstUrgent = $urgentComplaints->first())
                                        <p class="pd-note mb-0 text-danger">{{ $firstUrgent->priority_reason ?: 'Flagged urgent by AI' }}</p>
                                    @endif
                                    <p class="pd-note mb-2">{{ $urgentComplaints->count() }} urgent · {{ $oldComplaints }} waiting 7+ days @if ($oldComplaints > 0)(oldest {{ $oldestOpenComplaintDays }} {{ Str::plural('day', $oldestOpenComplaintDays) }})@endif</p>
                                    <a href="{{ route('proprietor.complaints.index', ['complaint_ids' => $attentionComplaintIds]) }}" class="btn btn-sm {{ $urgentComplaints->isNotEmpty() ? 'btn-outline-danger' : 'btn-outline-warning' }} mt-auto align-self-start"><i class="bi bi-eye" aria-hidden="true"></i> View</a>
                                @else
                                    <p class="pd-tile-ok mb-0"><i class="bi bi-check-circle" aria-hidden="true"></i> None right now</p>
                                    <p class="pd-note mb-0">AI-flagged or waiting 7+ days</p>
                                @endif
                                @break
                            @case('due')
                                <h3 class="pd-stat-label mb-1"><i class="bi bi-calendar-event" aria-hidden="true"></i> Rent due in 7 days</h3>
                                @if ($dueSoon->isNotEmpty())
                                    <div class="pd-tile-value">{{ $dueSoon->count() }}</div>
                                    <p class="pd-note mb-2">₱{{ number_format($dueSoonTotal, 2) }} · next {{ $dueSoon->first()->tenant->name }}, {{ $dueSoon->first()->due_date->format('M j') }}</p>
                                    <a href="{{ route('proprietor.payments.create', ['tenant_id' => $dueSoon->first()->tenant->id]) }}" class="btn btn-sm btn-outline-primary mt-auto align-self-start"><i class="bi bi-cash-coin" aria-hidden="true"></i> Record payment</a>
                                @else
                                    <p class="pd-tile-ok mb-0"><i class="bi bi-check-circle" aria-hidden="true"></i> Nothing due this week</p>
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
            <div class="col-12"><section class="card h-100" aria-labelledby="pd-follow"><div class="card-body">
                @php($textable = $followUps->filter(fn ($row) => $row->risk_level === 'high' && filled($row->tenant->guardian_contact_number))->count())
                {{-- Card header: title and subtitle on the left, actions on the right (same pattern as Trends). --}}
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h2 id="pd-follow" class="h6 fw-semibold mb-1">Tenants to follow up</h2>
                        <p class="pd-note mb-0">High risk: 2 or more warning signs · Medium: 1 · Sorted by risk, then amount owed</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        {{-- Secondary action first, main action last (rightmost). --}}
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('proprietor.settings.edit') }}#risk-thresholds" title="Change the rules for High and Medium risk (late months, grace period, days overdue)"><i class="bi bi-sliders me-1" aria-hidden="true"></i> Thresholds</a>
                        @if ($textable > 0)
                            <button type="button" class="btn btn-sm btn-primary" id="pd-sms-all" data-preview-url="{{ route('proprietor.dashboard.sms.bulk-preview') }}" data-url="{{ route('proprietor.dashboard.sms.bulk') }}" title="Text a payment reminder to the guardians of {{ $textable }} high-risk {{ Str::plural('tenant', $textable) }}. You can review and edit each message before sending."><i class="bi bi-chat-dots me-1" aria-hidden="true"></i> Send reminders ({{ $textable }})</button>
                        @endif
                    </div>
                </div>
                @if ($followUps->isNotEmpty())
                    {{-- Toolbar: risk filter on the left, search on the right. --}}
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3" id="pd-follow-filters">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Show risk level">
                            @php($filterHints = ['all' => 'Show every tenant to follow up', 'high' => 'Show only High risk: 2 or more warning signs', 'medium' => 'Show only Medium risk: 1 warning sign'])
                            @foreach (['all' => 'All', 'high' => 'High', 'medium' => 'Medium'] as $value => $label)
                                <button type="button" class="btn {{ $value === 'all' ? 'btn-primary' : 'btn-outline-primary' }}" data-level="{{ $value }}" aria-pressed="{{ $value === 'all' ? 'true' : 'false' }}" title="{{ $filterHints[$value] }}">
                                    {{ $label }} <span class="badge text-bg-light ms-1">{{ $value === 'all' ? $followUps->count() : $followUps->where('risk_level', $value)->count() }}</span>
                                </button>
                            @endforeach
                        </div>
                        <label class="visually-hidden" for="pd-follow-search">Search tenants to follow up</label>
                        <input type="search" class="form-control form-control-sm pd-follow-search" id="pd-follow-search" placeholder="Search name or room" autocomplete="off" title="Type a tenant's name or room number">
                    </div>
                @endif
                <div id="pd-sms-message" class="d-none alert" role="status" aria-live="polite" data-refresh-url="{{ route('proprietor.dashboard.sms.refresh') }}"></div>
                <div class="table-responsive"><table class="table align-middle pd-follow-table" data-per-page="5">
                    <caption class="visually-hidden">Active tenants with High or Medium payment risk</caption>
                    <thead><tr><th scope="col">Tenant</th><th scope="col" class="text-end">Owes</th><th scope="col">Last 6 months</th><th scope="col" class="text-end">Action</th></tr></thead>
                    <tbody>
                        @forelse ($followUps as $row)
                            {{-- The overdue balance shows under "Owes"; the line under the name gives the strongest other reason. --}}
                            @php($room = $row->tenant->currentAssignment?->bed?->room?->room_number)
                            @php($overdueNow = in_array('Has an overdue balance now', $row->reasons, true))
                            @php($others = array_values(array_diff($row->reasons, ['Has an overdue balance now'])))
                            @php($mainReason = collect($others)->first(fn ($reason) => str_ends_with($reason, 'in a row')) ?? ($others[0] ?? ($overdueNow ? 'Has an overdue balance now' : '')))
                            @php($billedMonths = $row->trend->filter(fn ($point) => $point['level'] !== null))
                            @php($trendLabel = $billedMonths->map(fn ($point) => $point['label'].': '.$point['level'].' risk')->implode(', '))
                            <tr data-level="{{ $row->risk_level }}" data-search="{{ Str::lower($row->tenant->name.' '.$room) }}">
                                <td>
                                    <div class="fw-semibold">{{ $row->tenant->name }}
                                        <span class="pd-tag pd-{{ $row->risk_level }} ms-1" tabindex="0" title="Why flagged: {{ implode('; ', $row->reasons) }}">{{ ucfirst($row->risk_level) }}</span>
                                    </div>
                                    <div class="pd-note">{{ $room ? 'Room '.$room : 'No room' }}@if ($mainReason) · <span class="pd-main-reason">{{ $mainReason }}</span>@endif</div>
                                    <span class="visually-hidden">Why flagged: {{ implode('; ', $row->reasons) }}</span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="{{ $row->owes > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">₱{{ number_format($row->owes, 2) }}</div>
                                    @if ($overdueNow)<div class="pd-overdue">overdue now</div>@endif
                                </td>
                                <td>
                                    @if ($billedMonths->isNotEmpty())
                                        <div class="pd-history" role="img" aria-label="{{ $trendLabel }}" title="{{ $trendLabel }}">
                                            @foreach ($billedMonths as $point)
                                                <span class="pd-history-cell pd-history-{{ $point['level'] }}"><span>{{ $point['label'] }}</span></span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="pd-note">Not billed yet</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex flex-column align-items-end gap-1">
                                        <div class="d-flex gap-1">
                                            @if ($row->risk_level === 'high' && filled($row->tenant->guardian_contact_number))
                                                @php($smsLabel = $row->sms_sent ? 'Send reminder again' : 'Send reminder')
                                                <button type="button" class="btn btn-sm btn-outline-primary pd-icon-btn pd-send-sms" title="{{ $smsLabel }}" aria-label="{{ $smsLabel }}"
                                                        data-url="{{ route('proprietor.dashboard.sms', $row->tenant) }}"
                                                        data-preview-url="{{ route('proprietor.dashboard.sms.preview', $row->tenant) }}"
                                                        data-log-id="{{ $row->sms_log_id }}"><i class="bi bi-chat-dots" aria-hidden="true"></i></button>
                                            @elseif ($row->risk_level === 'high')
                                                <button type="button" class="btn btn-sm btn-outline-secondary pd-icon-btn" disabled title="Guardian number missing" aria-label="Guardian number missing"><i class="bi bi-telephone-x" aria-hidden="true"></i></button>
                                            @endif
                                            <a class="btn btn-sm btn-outline-secondary pd-icon-btn" href="{{ route('proprietor.payments.history', $row->tenant) }}" title="Open ledger" aria-label="Open ledger for {{ $row->tenant->name }}"><i class="bi bi-journal-text" aria-hidden="true"></i></a>
                                        </div>
                                        <small class="text-muted pd-sms-status" data-tenant-id="{{ $row->tenant->id }}" aria-live="polite">{{ match ($row->sms_status) {
                                            'queued' => 'SMS queued',
                                            'unknown' => 'SMS unconfirmed',
                                            'delivered' => 'SMS delivered',
                                            'sent' => 'SMS sent',
                                            default => '',
                                        } }}</small>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-5"><i class="bi bi-check-circle text-success" aria-hidden="true"></i> No tenants need a payment follow-up right now.</td></tr>
                        @endforelse
                        @if ($followUps->isNotEmpty())
                            <tr class="d-none" id="pd-follow-none"><td colspan="4" class="text-center text-muted py-4">No tenants match this filter.</td></tr>
                        @endif
                    </tbody>
                </table></div>
                @if ($followUps->isNotEmpty())
                    <p class="pd-note mb-0">
                        Monthly risk:
                        <span class="pd-history-cell pd-history-low pd-history-key ms-1"></span> Low
                        <span class="pd-history-cell pd-history-medium pd-history-key ms-2"></span> Medium
                        <span class="pd-history-cell pd-history-high pd-history-key ms-2"></span> High
                        · Hover a risk badge for every reason · Reminders go to the guardian's mobile number.
                    </p>
                @endif
            </div></section></div>

        </div>

        <div class="row g-4 mt-1">
            <div class="col-12">
                {{-- REDESIGNED COMPLAINT SUMMARY SECTION --}}
                <section class="card h-100" aria-labelledby="pd-summary">
                    <div class="card-body p-4">

                        {{-- Header --}}
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 border-bottom pb-2">
                            <h2 id="pd-summary" class="h6 fw-semibold mb-0">Complaint Summary</h2>
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
                            <div id="pd-summary-content" @if ($topIssue && !empty($topIssue['complaint_ids'])) data-summary-url="{{ route('proprietor.complaints.index', ['complaint_ids' => $topIssue['complaint_ids']]) }}" @endif>
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

                                {{-- Issues grouped by the category the AI read from each complaint --}}
                                @if (!empty($summaryContent['categories']))
                                    <div class="d-flex flex-column gap-3">
                                        @foreach ($summaryContent['categories'] as $group)
                                            <div class="pd-summary-group">
                                                <h3 class="h6 fw-semibold mb-1 d-flex align-items-center gap-2" style="font-size: 0.85rem;">
                                                    {{ $group['label'] }}
                                                    <span class="text-muted fw-normal small">{{ $group['count'] }} {{ Str::plural('complaint', $group['count']) }}</span>
                                                </h3>
                                                <ul class="list-unstyled mb-0 d-flex flex-column gap-1 ps-1">
                                                    @foreach ($group['issues'] as $theme)
                                                        <li>
                                                            @if (!empty($theme['complaint_ids']))
                                                                <a class="pd-summary-theme-link" href="{{ route('proprietor.complaints.index', ['complaint_ids' => $theme['complaint_ids']]) }}" title="View the complaints about this issue">{{ $theme['theme'] }}</a>
                                                            @else
                                                                <span class="text-dark fw-medium small">{{ $theme['theme'] }}</span>
                                                            @endif
                                                            <span class="badge bg-secondary ms-1 rounded-pill" style="font-size: 0.6rem;">{{ $theme['count'] }}</span>
                                                            @if (!empty($theme['detail']))<div class="small text-muted">{{ $theme['detail'] }}</div>@endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endforeach
                                    </div>
                                    @if (!empty($summaryContent['recategorized']))
                                        @php($recategorized = collect($summaryContent['recategorized']))
                                        <div class="alert alert-warning small mt-3 mb-0 py-2">
                                            <strong>{{ $recategorized->count() }} {{ Str::plural('complaint', $recategorized->count()) }} filed under a different category.</strong>
                                            The AI sorted {{ $recategorized->count() === 1 ? 'it' : 'them' }} by what the tenant actually wrote:
                                            <ul class="mb-0 mt-1 ps-3">
                                                @foreach ($recategorized as $moved)
                                                    <li><a href="{{ route('proprietor.complaints.index', ['complaint_ids' => [$moved['complaint_id']]]) }}">Complaint #{{ $moved['complaint_id'] }}</a>: filed as {{ \App\Services\ComplaintSummarizationService::CATEGORY_LABELS[$moved['filed_as']] ?? ucfirst($moved['filed_as']) }}, reads as {{ \App\Services\ComplaintSummarizationService::CATEGORY_LABELS[$moved['category']] ?? ucfirst($moved['category']) }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                @else
                                    {{-- Fallback for raw text, which will be formatted by JS on load --}}
                                    <div class="text-muted small raw-summary-text" data-raw-text="{{ $summaryContent['text'] ?? '' }}" style="white-space: pre-line; max-height: 200px; overflow-y: auto;">
                                        {{ $summaryContent['text'] ?? '' }}
                                    </div>
                                @endif
                                @if ($latestSummary && !empty($latestSummary->filter_params['complaint_ids']))
                                    <a class="btn btn-sm btn-outline-primary mt-3" href="{{ route('proprietor.complaints.index', ['summary_id' => $latestSummary->id]) }}">
                                        <i class="bi bi-list-ul" aria-hidden="true"></i> View all {{ count($latestSummary->filter_params['complaint_ids']) }} complaints in this summary
                                    </a>
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
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg" aria-hidden="true"></i> Cancel</button>
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
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg" aria-hidden="true"></i> Close</button>
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
                const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (char) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
                })[char]);
                const summaryUrl = container.dataset.summaryUrl;

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
                        const text = summaryUrl
                            ? `<a class="pd-summary-theme-link" href="${escapeHtml(summaryUrl)}" title="View complaints included in this summary">${escapeHtml(issue.text)}</a>`
                            : `<span class="text-dark fw-medium small">${escapeHtml(issue.text)}</span>`;
                        html += `
                            <li class="d-flex align-items-start gap-2">
                                <div class="mt-1 text-primary" style="font-size: 1.2rem; line-height: 1;">&bull;</div>
                                <div>
                                    ${text}
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
