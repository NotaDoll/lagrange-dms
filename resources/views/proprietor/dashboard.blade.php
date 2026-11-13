@extends('layouts.proprietor')
@section('title', 'Dashboard')
@section('content')
    @include('proprietor.dashboard-styles')
    <div class="pd" id="proprietor-dashboard">
        <header class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div><h1 class="h3 fw-bold mb-1">Dashboard</h1><p class="text-muted mb-0">{{ today()->format('l, F j, Y') }}</p></div>
        </header>
        <section class="card mb-4" aria-labelledby="pd-priority"><div class="card-body">
            <h2 id="pd-priority" class="h6 fw-semibold mb-3">Priority alerts</h2>
            <ul class="pd-alerts list-unstyled mb-0">
                <li><span class="pd-tag pd-high">HIGH RISK</span><a href="{{ route('proprietor.payments.index') }}">{{ $highRiskCount }} {{ Str::plural('tenant', $highRiskCount) }} {{ $highRiskCount === 1 ? 'is' : 'are' }} flagged high-risk for payment default.</a></li>
                @if ($latestSummary)
                <li><span class="pd-tag pd-ai">AI SUMMARY</span><a href="{{ route('proprietor.complaints.index') }}">{{ $summaryContent['alert'] }}</a></li>
                @endif
                <li><span class="pd-tag pd-medium">OVERDUE</span><a href="{{ route('proprietor.complaints.index') }}">{{ $oldComplaints }} {{ Str::plural('complaint', $oldComplaints) }} {{ $oldComplaints === 1 ? 'has' : 'have' }} been open for more than 7 days.</a></li>
                <li><span class="pd-tag pd-room">ROOMS</span><a href="{{ route('proprietor.rooms.index') }}">{{ $reservedRooms }} {{ Str::plural('room', $reservedRooms) }} marked reserved, {{ $upcomingMoveIns }} upcoming {{ Str::plural('move-in', $upcomingMoveIns) }}@if ($nextMoveIn) (next {{ \Illuminate\Support\Carbon::parse($nextMoveIn)->format('M j') }})@endif, and {{ $availableBeds }} {{ Str::plural('bed', $availableBeds) }} available.</a></li>
            </ul>
        </div></section>

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

        <div class="row g-4">
            <div class="col-xl-7"><section class="card h-100" aria-labelledby="pd-follow"><div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"><h2 id="pd-follow" class="h6 fw-semibold mb-0">Tenants to follow up</h2><a class="btn btn-outline-primary" href="{{ route('proprietor.settings.edit') }}#risk-thresholds">Risk thresholds</a></div>
                <p class="pd-note">Ranked by rules tripped. High: 2–4 rules; Medium: 1; Low: 0. Trend uses saved monthly flags; gaps mean no record.</p>
                <div id="pd-sms-message" class="d-none alert" role="status" aria-live="polite"></div>
                <div class="table-responsive"><table class="table align-middle pd-follow-table">
                    <caption class="visually-hidden">Active tenants with High or Medium payment risk</caption>
                    <thead><tr><th scope="col">Tenant</th><th scope="col">Room</th><th scope="col">Rules tripped</th><th scope="col">Risk level</th><th scope="col">6-month trend</th><th scope="col">Action</th></tr></thead>
                    <tbody>
                        @forelse ($followUps as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row->tenant->name }}</td>
                                <td>{{ $row->tenant->currentAssignment?->bed?->room?->room_number ?? 'Unassigned' }}</td>
                                <td><span title="{{ implode(', ', array_keys(array_filter($row->rules))) }}">{{ $row->rules_tripped }} of 4</span><details class="pd-note"><summary>Rule details</summary><ul class="ps-3 mb-0">@foreach (array_keys(array_filter($row->rules)) as $rule)<li>{{ $rule }}</li>@endforeach</ul></details></td>
                                <td><span class="pd-tag pd-{{ $row->risk_level }}">{{ ucfirst($row->risk_level) }}</span></td>
                                <td>
                                    @php
                                        $previousPoint = null;
                                        $segments = [];
                                        $points = [];
                                        foreach ($row->trend as $index => $point) {
                                            if (!$point['level']) { $previousPoint = null; continue; }
                                            $position = ['low' => 30, 'medium' => 19, 'high' => 8][$point['level']];
                                            $currentPoint = ['x' => 7 + $index * 18, 'y' => $position];
                                            if ($previousPoint) { $segments[] = [$previousPoint, $currentPoint]; }
                                            $points[] = $currentPoint;
                                            $previousPoint = $currentPoint;
                                        }
                                        $trendLabel = $row->trend->map(fn ($point) => $point['label'].': '.($point['level'] ?? 'no record'))->implode(', ');
                                    @endphp
                                    <svg class="pd-spark" viewBox="0 0 105 40" role="img" aria-label="{{ $trendLabel }}"><title>{{ $trendLabel }}</title>
                                        @foreach ($segments as [$start, $end])<line x1="{{ $start['x'] }}" y1="{{ $start['y'] }}" x2="{{ $end['x'] }}" y2="{{ $end['y'] }}" stroke="currentColor" stroke-width="2"/>@endforeach
                                        @foreach ($points as $point)<circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3" fill="currentColor"/>@endforeach
                                    </svg><span class="pd-note d-block">{{ $points ? $row->trend->first()['label'].'–'.$row->trend->last()['label'] : 'No saved history' }}</span>
                                </td>
                                <td>
                                    @if ($row->risk_level === 'high' && filled($row->tenant->guardian_contact_number))
                                        <div class="d-inline-flex flex-column align-items-start gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-primary text-nowrap pd-send-sms"
                                                    data-url="{{ route('proprietor.dashboard.sms', $row->tenant) }}"
                                                    data-log-id="{{ $row->sms_log_id }}">
                                                {{ $row->sms_sent ? 'Send SMS again' : 'Send SMS' }}
                                            </button>
                                            <small class="text-muted pd-sms-status" aria-live="polite">{{ match ($row->sms_status) {
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
                                    <a class="btn btn-sm btn-link" href="{{ route('proprietor.payments.index', ['tenant_id' => $row->tenant->id]) }}">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5">No active tenants currently trip any payment risk rules.</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
                <p class="pd-note mb-0">Payment reminders go to the mobile number saved for the guardian.</p>
            </div></section></div>

            <div class="col-xl-5 d-flex flex-column gap-4">

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

                <section class="card" aria-labelledby="pd-floor"><div class="card-body">
                    <h2 id="pd-floor" class="h6 fw-semibold mb-3">Occupancy by floor</h2>
                    @forelse ($floors as $floor)
                        @php($percent = $floor->total ? round($floor->occupied / $floor->total * 100) : 0)
                        <div class="mb-3"><div class="d-flex justify-content-between small mb-2"><span>Floor {{ $floor->label }}</span><span>{{ $floor->occupied }} / {{ $floor->total }} occupied</span></div><div class="progress pd-progress" role="progressbar" aria-label="Floor {{ $floor->label }} occupancy" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: {{ $percent }}%"></div></div></div>
                    @empty
                        <p class="text-muted mb-0">No beds have been added to any floor yet.</p>
                    @endforelse
                </div></section>
            </div>
        </div>
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
@endsection
