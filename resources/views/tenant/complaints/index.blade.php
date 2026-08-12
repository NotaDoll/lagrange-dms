@extends('layouts.tenant')
@section('title', 'My Complaints')
@section('content')

<style>
    /* ===== COMPLAINTS VARIABLES ===== */
    :root {
        --cp-plum: #5E1049;
        --cp-plum-hover: #4A0D39;
        --cp-pink-light: #FDF2F8;
        --cp-border: #E8DEE5;
    }

    /* ===== HEADER ===== */
    .cp-header h1 {
        font-size: 1.6rem;
        font-weight: 700;
        color: #1a1a1a;
        margin-bottom: 4px;
    }

    .cp-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 24px;
    }

    .cp-header p {
        color: #6b7280;
        font-size: 0.9rem;
        margin-bottom: 0;
    }

    .cp-submit-btn {
        background: var(--cp-plum);
        border: none;
        color: #fff;
        font-weight: 600;
        font-size: 0.88rem;
        padding: 10px 22px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: 0.2s;
        box-shadow: 0 2px 4px rgba(94, 16, 73, 0.15);
    }
    .cp-submit-btn:hover { background: var(--cp-plum-hover); color: #fff; }
    .cp-submit-btn svg { width: 18px; height: 18px; }

    /* ===== MAIN CARD ===== */
    .cp-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid var(--cp-border);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }

    /* ===== FILTER BAR ===== */
    .cp-filter-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        padding: 16px 24px;
        border-bottom: 1px solid #f3f4f6;
    }

    .cp-filter-label { font-size: 0.85rem; font-weight: 600; color: #333; margin-right: 4px; }

    .cp-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #fff;
        color: #333;
        border: 1px solid var(--cp-border);
        border-radius: 999px;
        padding: 3px 12px 3px 14px;
        font-size: 0.8rem;
        font-weight: 500;
        text-decoration: none;
        transition: 0.2s;
    }
    .cp-chip:hover { background: #f9f9f9; }

    .cp-clear-link {
        font-size: 0.8rem;
        color: #3b82f6;
        text-decoration: underline;
        margin-left: 4px;
        font-weight: 500;
    }

    /* Filter Dropdown Button */
    .cp-filter-btn {
        border: 1px solid var(--cp-plum);
        background: transparent;
        color: var(--cp-plum);
        border-radius: 999px;
        padding: 6px 18px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: 0.2s;
    }
    .cp-filter-btn:hover { background: #FDF2F8; }
    .cp-filter-btn svg { width: 14px; height: 14px; fill: currentColor; }

    /* ===== FILTER DROPDOWN MENU ===== */
    .cp-filter-dropdown {
        min-width: 300px;
        padding: 20px;
        border-radius: 16px;
        border: none;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        margin-top: 12px !important;
        background: #fff;
    }

    .cp-filter-dropdown .filter-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }
    .cp-filter-dropdown .filter-header h6 {
        font-weight: 700;
        font-size: 1.1rem;
        margin: 0;
    }
    .cp-filter-dropdown .filter-clear {
        font-size: 0.8rem;
        color: #3b82f6;
        text-decoration: none;
        font-weight: 500;
    }
    .cp-filter-dropdown .filter-section-title {
        font-size: 0.8rem;
        font-weight: 700;
        color: #a855a7;
        text-transform: uppercase;
        margin-bottom: 10px;
    }

    .cp-filter-dropdown .form-check-label {
        font-weight: 500;
        color: var(--cp-plum);
        font-size: 0.9rem;
    }
    .cp-filter-dropdown .form-check-input {
        border-color: #d1d5db;
    }
    .cp-filter-dropdown .form-check-input:checked {
        background-color: var(--cp-plum);
        border-color: var(--cp-plum);
    }
    .cp-filter-dropdown .form-select {
        border-radius: 6px;
        padding: 10px 12px;
        font-weight: 500;
        border: 1px solid #d1d5db;
    }

    .cp-filter-apply-btn {
        background: var(--cp-plum);
        color: white;
        border-radius: 999px;
        padding: 10px;
        font-weight: 600;
        border: none;
        width: 100%;
        margin-top: 15px;
        transition: 0.2s;
    }
    .cp-filter-apply-btn:hover { background: var(--cp-plum-hover); color: white; }

    /* ===== TABLE ===== */
    .cp-table thead th {
        background-color: var(--cp-pink-light);
        color: var(--cp-plum);
        font-weight: 700;
        font-size: 0.85rem;
        border: none;
        padding: 14px 24px;
    }

    .cp-table tbody td {
        padding: 16px 24px;
        font-size: 0.88rem;
        color: #374151;
        border-bottom: 1px solid #f3f4f6;
        vertical-align: middle;
    }
    .cp-table tbody tr:last-child td { border-bottom: none; }

    .cp-status { font-size: 0.85rem; font-weight: 500; }
    .cp-status.submitted { color: #2563eb; }
    .cp-status.in_progress { color: #ea580c; }
    .cp-status.resolved { color: #16a34a; }

    .cp-row-menu { border: none; background: none; color: #9ca3af; font-size: 1.2rem; padding: 4px 8px; cursor: pointer; }
    .cp-row-menu:hover { color: var(--cp-plum); }

    /* ===== FOOTER ===== */
    .cp-footer {
        background-color: var(--cp-pink-light);
        display: flex; align-items: center; justify-content: space-between;
        padding: 12px 24px; font-size: 0.85rem; color: #6b7280;
        border-top: 1px solid #f3f4f6;
    }

    .cp-pager { display: flex; align-items: center; gap: 8px; }
    .cp-pager-btn {
        width: 28px; height: 28px; border-radius: 50%;
        border: 1px solid var(--cp-border); background: #fff;
        display: flex; align-items: center; justify-content: center;
        color: var(--cp-plum); text-decoration: none; font-size: 0.8rem; transition: 0.2s;
    }
    .cp-pager-btn:hover:not(.disabled) { background: #FDF2F8; border-color: var(--cp-plum); }
    .cp-pager-btn.disabled { opacity: 0.5; pointer-events: none; }

    .cp-pager-current {
        width: 28px; height: 28px; border-radius: 50%;
        background: var(--cp-plum); color: #fff; font-weight: 600;
        display: flex; align-items: center; justify-content: center; font-size: 0.8rem;
    }

    @media (max-width: 768px) {
        .cp-filter-bar { flex-direction: column; align-items: stretch; }
        .cp-filter-btn { align-self: flex-end; }
        .cp-footer { flex-direction: column; gap: 10px; text-align: center; }
    }
</style>

<!-- Header -->
<div class="container mt-4">
    <div class="cp-header">
        <div>
            <h1>My Complaints</h1>
            <p>Track the status of your maintenance and service requests.</p>
        </div>
        <a href="{{ route('tenant.complaints.create') }}" class="cp-submit-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
            </svg>
            Submit Complaint
        </a>
    </div>

    <!-- Main Card -->
    <div class="cp-card">

        <!-- Filter Bar -->
        <div class="cp-filter-bar">
            <div class="d-flex align-items-center flex-wrap gap-2">
                @php
                    $activeStatus = (array) request('status', []);
                    $activeCategory = (array) request('category', []);
                    $hasFilters = count(array_filter($activeStatus)) > 0 || count(array_filter($activeCategory)) > 0;
                @endphp

                @if ($hasFilters)
                    <span class="cp-filter-label">Active Filters:</span>
                    @foreach ($activeStatus as $s)
                        <a href="{{ request()->fullUrlWithoutQuery('status') }}" class="cp-chip">{{ Str::headline($s) }} <span style="color:#9ca3af; font-size:1.1rem;">&times;</span></a>
                    @endforeach
                    @foreach ($activeCategory as $c)
                        <a href="{{ request()->fullUrlWithoutQuery('category') }}" class="cp-chip">{{ ucfirst($c) }} <span style="color:#9ca3af; font-size:1.1rem;">&times;</span></a>
                    @endforeach
                    <span class="text-muted mx-1">|</span>
                    <a href="{{ url()->current() }}" class="cp-clear-link">Clear all filters</a>
                @else
                    <span class="cp-filter-label">Active Filters:</span>
                @endif
            </div>

            <!-- Filter Dropdown -->
            <div class="dropdown">
                <button class="cp-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h10M4 18h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Filter
                </button>

                <form method="GET" class="dropdown-menu cp-filter-dropdown">
                    <div class="filter-header">
                        <h6>Filter Complaints</h6>
                        <a href="{{ url()->current() }}" class="filter-clear">Clear all</a>
                    </div>

                    <div class="mb-3">
                        <div class="filter-section-title">Status</div>
                        <div class="row gx-2">
                            @foreach (['submitted' => 'Submitted', 'in_progress' => 'In Progress', 'resolved' => 'Resolved'] as $val => $label)
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="status[]" value="{{ $val }}" id="st-{{ $val }}" {{ in_array($val, $activeStatus) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="st-{{ $val }}">{{ $label }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="filter-section-title">Category</div>
                        <div class="row gx-2">
                            @foreach (['maintenance' => 'Maintenance', 'noise' => 'Noise', 'billing' => 'Billing', 'other' => 'Others'] as $val => $label)
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="category[]" value="{{ $val }}" id="cat-{{ $val }}" {{ in_array($val, $activeCategory) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="cat-{{ $val }}">{{ $label }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mb-0">
                        <div class="filter-section-title">Date Submitted</div>
                        <select name="date_filter" class="form-select">
                            <option value="30">Last 30 days</option>
                            <option value="7">Last 7 days</option>
                            <option value="90">Last 90 days</option>
                            <option value="all">All time</option>
                        </select>
                    </div>

                    <button type="submit" class="cp-filter-apply-btn">Apply Filter</button>
                </form>
            </div>
        </div>

        <!-- Table -->
        <div class="table-responsive">
            <table class="table cp-table mb-0">
                <thead>
                    <tr>
                        <th style="width: 18%;">Category</th>
                        <th style="width: 42%;">Description</th>
                        <th style="width: 18%;">Status</th>
                        <th style="width: 18%;">Timestamp</th>
                        <th style="width: 4%;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($complaints as $complaint)
                        <tr>
                            <td>{{ ucfirst($complaint->category) }}</td>
                            <td>{{ Str::limit($complaint->description, 90) }}</td>
                            <td>
                                <span class="cp-status {{ $complaint->status }}">
                                    {{ Str::headline($complaint->status) }}
                                </span>
                            </td>
                            <td>{{ $complaint->created_at->format('m/d/y  h:i A') }}</td>
                            <td class="text-end">
                                <button class="cp-row-menu" type="button">&#8942;</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">No complaints submitted yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        @if ($complaints->total() > 0)
            <div class="cp-footer">
                <div>
                    Showing <strong>{{ $complaints->firstItem() }}-{{ $complaints->lastItem() }}</strong> of {{ $complaints->total() }} complaints
                </div>
                <div class="cp-pager">
                    <a href="{{ $complaints->previousPageUrl() ?? '#' }}" class="cp-pager-btn {{ $complaints->onFirstPage() ? 'disabled' : '' }}">&#8592;</a>
                    <span class="cp-pager-current">{{ $complaints->currentPage() }}</span>
                    <a href="{{ $complaints->nextPageUrl() ?? '#' }}" class="cp-pager-btn {{ $complaints->hasMorePages() ? '' : 'disabled' }}">&#8594;</a>
                </div>
            </div>
        @endif

    </div>
</div>
@endsection
