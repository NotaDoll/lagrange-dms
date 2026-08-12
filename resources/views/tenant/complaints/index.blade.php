@extends('layouts.tenant')
@section('title', 'My Complaints')
@section('content')


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
                        @php
                            $newStatus = array_diff($activeStatus, [$s]);
                            $queryParams = request()->query();
                            $queryParams['status'] = $newStatus;
                            if(empty($queryParams['status'])) unset($queryParams['status']);
                        @endphp
                        <a href="{{ request()->url() . '?' . http_build_query($queryParams) }}" class="cp-chip">
                            {{ Str::headline($s) }} <span style="color:#9ca3af; font-size:1.1rem;">&times;</span>
                        </a>
                    @endforeach

                    @foreach ($activeCategory as $c)
                        @php
                            $newCategory = array_diff($activeCategory, [$c]);
                            $queryParams = request()->query();
                            $queryParams['category'] = $newCategory;
                            if(empty($queryParams['category'])) unset($queryParams['category']);
                        @endphp
                        <a href="{{ request()->url() . '?' . http_build_query($queryParams) }}" class="cp-chip">
                            {{ ucfirst($c) }} <span style="color:#9ca3af; font-size:1.1rem;">&times;</span>
                        </a>
                    @endforeach

                    <span class="text-muted mx-1">|</span>
                    <a href="{{ request()->url() }}" class="cp-clear-link">Clear all filters</a>
                @else
                    <span class="cp-filter-label">Active Filters:</span>
                @endif
            </div>

            <!-- Custom Filter Button & Popup -->
            <div class="cp-filter-wrapper">
                <button class="cp-filter-btn" onclick="openFilterPopup()">
                    <svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h10M4 18h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Filter
                </button>

                <!-- Overlay -->
                <div class="cp-filter-overlay" id="filterOverlay" onclick="closeFilterPopup()"></div>

                <!-- Popup Form -->
                <div class="cp-filter-popup" id="filterPopup">
                    <form method="GET" action="{{ request()->url() }}">
                        <div class="filter-header">
                            <h6>Filter Complaints</h6>
                            <a href="{{ request()->url() }}" class="filter-clear" onclick="closeFilterPopup()">Clear all</a>
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
                                <option value="30" {{ request('date_filter') == '30' ? 'selected' : '' }}>Last 30 days</option>
                                <option value="7" {{ request('date_filter') == '7' ? 'selected' : '' }}>Last 7 days</option>
                                <option value="90" {{ request('date_filter') == '90' ? 'selected' : '' }}>Last 90 days</option>
                                <option value="all" {{ request('date_filter') == 'all' ? 'selected' : '' }}>All time</option>
                            </select>
                        </div>

                        <button type="submit" class="cp-filter-apply-btn" onclick="closeFilterPopup()">Apply Filter</button>
                    </form>
                </div>
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
                    {{-- Previous Page --}}
                    @if ($complaints->onFirstPage())
                        <span class="cp-pager-btn disabled">&#8592;</span>
                    @else
                        <a href="{{ $complaints->previousPageUrl() }}" class="cp-pager-btn">&#8592;</a>
                    @endif

                    {{-- Current Page --}}
                    <span class="cp-pager-current">{{ $complaints->currentPage() }}</span>

                    {{-- Next Page --}}
                    @if ($complaints->hasMorePages())
                        <a href="{{ $complaints->nextPageUrl() }}" class="cp-pager-btn">&#8594;</a>
                    @else
                        <span class="cp-pager-btn disabled">&#8594;</span>
                    @endif
                </div>
            </div>
        @endif

    </div>
</div>

<!-- ===== JAVASCRIPT TO HANDLE POPUP ===== -->
<script>
    function openFilterPopup() {
        document.getElementById('filterPopup').classList.add('open');
        document.getElementById('filterOverlay').classList.add('open');
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
    }

    function closeFilterPopup() {
        document.getElementById('filterPopup').classList.remove('open');
        document.getElementById('filterOverlay').classList.remove('open');
        document.body.style.overflow = ''; // Restore background scrolling
    }

    // Close popup if the user presses the ESC key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeFilterPopup();
        }
    });
</script>

@endsection
