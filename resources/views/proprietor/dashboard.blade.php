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
                                <td class="fw-semibold">{{ $row->tenant->user->name }}</td>
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
                                    @if ($row->risk_level === 'high')
                                        <button type="button" class="btn btn-sm btn-outline-primary text-nowrap pd-send-sms" data-url="{{ route('proprietor.dashboard.sms', $row->tenant) }}" @disabled($row->sms_sent)>{{ $row->sms_sent ? 'SMS sent' : 'Send SMS' }}</button>
                                    @endif
                                    <a class="btn btn-sm btn-link" href="{{ route('proprietor.payments.index', ['tenant_id' => $row->tenant->id]) }}">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5">No active tenants currently trip any payment risk rules.</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
                <p class="pd-note mb-0">SMS is sent only to the tenant. Guardian delivery is unavailable because both consent records are not present.</p>
            </div></section></div>
            <div class="col-xl-5 d-flex flex-column gap-4">
                <section class="card" aria-labelledby="pd-summary"><div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h2 id="pd-summary" class="h6 fw-semibold mb-0">Complaint summary</h2>@if ($latestSummary)<span class="pd-tag pd-ai">AI-generated</span>@endif</div>
                    @if ($latestSummary)
                        <p class="pd-note">Saved {{ $latestSummary->created_at->format('M j, Y g:i A') }}@if ($summaryContent['total'] !== null) · {{ $summaryContent['total'] }} open complaints at generation @endif </p>
                        @if ($summaryContent['themes'])
                            <ul class="ps-3">@foreach ($summaryContent['themes'] as $theme)<li class="mb-2">{{ $theme['theme'] }} <strong>({{ $theme['count'] }} {{ Str::plural('complaint', $theme['count']) }})</strong></li>@endforeach</ul>
                        @else
                            <p class="pd-summary-text">{{ $summaryContent['text'] }}</p>
                        @endif
                        @if ($summaryContent['action'])<h3 class="h6">Recommended action</h3><p>{{ $summaryContent['action'] }}</p>@endif
                    @endif
                    @if ($latestSummary)<p class="pd-note">May contain inaccuracies. Review before taking action.</p>@endif
                    <div id="pd-summary-message" class="d-none alert" role="status" aria-live="polite"></div>
                    <button type="button" class="btn btn-primary" id="pd-generate-summary" data-url="{{ route('proprietor.dashboard.summary') }}">Generate new summary</button>
                </div></section>
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
@endsection
