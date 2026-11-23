@extends('layouts.proprietor')
@section('title', 'Dashboard')
@section('content')
    @include('proprietor.dashboard-styles')
    <div class="pd" id="proprietor-dashboard">
        <header class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div><h1 class="h3 fw-bold mb-1">Dashboard</h1><p class="text-muted mb-0">{{ today()->format('l, F j, Y') }}</p></div>
        </header>
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3"><section class="card h-100 {{ $overdueBalance > 0 ? 'pd-tile-danger' : '' }}"><div class="card-body">
                <h2 class="pd-stat-label"><i class="bi bi-exclamation-triangle text-danger" aria-hidden="true"></i> Overdue balance</h2><div class="pd-stat-value text-danger">₱{{ number_format($overdueBalance, 2) }}</div>
                <a href="{{ route('proprietor.payments.index', ['status' => 'overdue']) }}" class="small">{{ $overdueTenants }} {{ Str::plural('tenant', $overdueTenants) }} with overdue charges</a><p class="pd-note mb-0 mt-1">Unpaid charges due before today.</p>
            </div></section></div>
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
                <h2 class="pd-stat-label">Open complaints</h2><div class="pd-stat-value">{{ $openComplaints }}</div>
                <a href="{{ route('proprietor.complaints.index') }}" class="small">{{ $urgentComplaints->count() }} urgent · {{ $oldComplaints }} waiting 7+ days</a><p class="pd-note mb-0 mt-1">{{ $newComplaintsToday }} new today.</p>
            </div></section></div>
        </div>

        @php
            $dueSoonTotal = (float) $dueSoon->sum('amount');
            $topIssue = $summaryContent['themes'][0] ?? null;
            $attentionCount = count($attentionComplaintIds);
            // Each tile: danger (act now), warning (check soon), info (good to know), or clear (nothing to do).
            $tiles = collect([
                ['key' => 'overdue', 'level' => $overdueTenants > 0 ? 'danger' : 'clear'],
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
                            @case('overdue')
                                <h3 class="pd-stat-label mb-1"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Tenants with overdue charges</h3>
                                @if ($overdueTenants > 0)
                                    <div class="pd-tile-value">{{ $overdueTenants }}</div>
                                    <p class="pd-note mb-2">₱{{ number_format($overdueBalance, 2) }} unpaid past due</p>
                                    <a href="{{ route('proprietor.payments.index', ['status' => 'overdue']) }}" class="btn btn-sm btn-outline-danger mt-auto align-self-start"><i class="bi bi-cash-stack" aria-hidden="true"></i> Review overdue</a>
                                @else
                                    <p class="pd-tile-ok mb-0"><i class="bi bi-check-circle" aria-hidden="true"></i> No overdue charges</p>
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

        <div class="row g-4">
            <div class="col-12"><section class="card h-100" aria-labelledby="pd-follow"><div class="card-body">
                @include('proprietor.partials.follow-up', ['perPage' => 5, 'showViewAll' => true])
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
    @include('proprietor.partials.follow-up-modals')

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

@endsection
