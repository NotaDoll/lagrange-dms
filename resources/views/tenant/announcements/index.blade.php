@extends('layouts.tenant')

@section('title', 'Announcements')

@section('content')


<div class="ann-page-wrapper">

    <!-- ===== LEFT COLUMN ===== -->
    <div class="ann-left-col">

        <div class="ann-header">
            <h1>Announcements</h1>
            <p>Stay updated with the latest dorm news and advisories.</p>
        </div>

        <!-- Controls -->
        <div class="ann-controls">
            <div class="ann-tabs">
                <a href="{{ route('tenant.announcements.index') }}" class="{{ request('status') !== 'unread' ? 'active' : '' }}">All</a>
                <a href="{{ route('tenant.announcements.index', ['status' => 'unread']) }}" class="{{ request('status') === 'unread' ? 'active' : '' }}">Unread</a>
            </div>

            <div class="ann-actions">
                <button class="ann-btn-outline">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Mark all as read
                </button>

                <!-- Filter Dropdown -->
                <div class="dropdown">
                    <button class="ann-btn-solid dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M4 6h16M4 12h10M4 18h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        Filter
                    </button>

                    <form method="GET" class="dropdown-menu ann-filter-dropdown">
                        <div class="ann-filter-header">
                            <h6>Filter Announcements</h6>
                            <a href="{{ url()->current() }}" class="ann-filter-clear">Clear all</a>
                        </div>

                        <div class="mb-3">
                            <div class="ann-filter-section-title">Status</div>
                            <div class="row gx-2">
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="status[]" value="unread" id="st-unread">
                                        <label class="form-check-label" for="st-unread">Unread</label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="status[]" value="read" id="st-read">
                                        <label class="form-check-label" for="st-read">Read</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-0">
                            <div class="ann-filter-section-title">Date Published</div>
                            <select name="date_filter" class="form-select">
                                <option value="30">Last 30 days</option>
                                <option value="7">Last 7 days</option>
                                <option value="90">Last 90 days</option>
                                <option value="all">All time</option>
                            </select>
                        </div>

                        <button type="submit" class="ann-filter-apply-btn">Apply Filter</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- List -->
        <div class="ann-list">
            @forelse ($announcements as $announcement)
                <div class="ann-item">
                    <div class="ann-item-header">
                        <h4 class="ann-item-title">{{ $announcement->title }}</h4>
                        <span class="ann-item-date">{{ $announcement->created_at->format('m/d/y  h:i A') }}</span>
                    </div>
                    <div class="ann-item-body">
                        {{ Str::limit($announcement->body, 120) }}
                    </div>
                    <div class="ann-item-footer">
                        <button class="ann-read-more" data-bs-toggle="modal" data-bs-target="#viewAnnouncementModal"
                                data-title="{{ $announcement->title }}"
                                data-date="{{ $announcement->created_at->format('F j, Y') }}"
                                data-time="{{ $announcement->created_at->format('g:i A') }}"
                                data-body="{{ $announcement->body }}">
                            Read more
                        </button>
                    </div>
                </div>
            @empty
                <p class="text-muted">No announcements yet.</p>
            @endforelse
        </div>

        <!-- Pagination -->
        <div class="ann-pagination">
            <div>
                Showing <strong>{{ $announcements->firstItem() ?? 0 }}-{{ $announcements->lastItem() ?? 0 }}</strong> of {{ $announcements->total() }} announcements
            </div>
            <div class="ann-pager">
                @if ($announcements->onFirstPage())
                    <span class="ann-pager-btn disabled">&#8592;</span>
                @else
                    <a href="{{ $announcements->previousPageUrl() }}" class="ann-pager-btn">&#8592;</a>
                @endif

                <span class="ann-pager-current">{{ $announcements->currentPage() }}</span>

                @if ($announcements->hasMorePages())
                    <a href="{{ $announcements->nextPageUrl() }}" class="ann-pager-btn">&#8594;</a>
                @else
                    <span class="ann-pager-btn disabled">&#8594;</span>
                @endif
            </div>
        </div>
    </div>

    <!-- ===== RIGHT COLUMN (SIDEBAR) ===== -->
    <div class="ann-right-col">

        <!-- FAQ Card -->
        <div class="ann-sidebar-card">
            <div class="ann-sidebar-title">Frequently Asked Questions</div>

            @forelse ($faqs as $faq)
                <div style="margin-bottom: 10px; border-bottom: 1px solid #f3f4f6; padding-bottom: 10px;">
                    <a href="{{ route('tenant.faq.index') }}" style="text-decoration: none; color: #374151; font-weight: 500; font-size: 0.9rem; display: block;">
                        {{ $faq->question ?? $faq->title }}
                    </a>
                </div>
            @empty
                <div style="color: #9ca3af; font-size:0.9rem;">No FAQs available yet.</div>
            @endforelse

            <a href="{{ route('tenant.faq.index') }}" style="display: block; text-align: right; margin-top: 15px; font-size: 0.8rem; color: #6b7280; text-decoration: none;">
                View all FAQs &rarr;
            </a>
        </div>

        <!-- Emergency Contact Card -->
        <div class="ann-emergency-box">
            <h5>Emergency Contact</h5>
            <p>For urgent room issues, call the dorm manager.</p>
            <div class="contact-person">Merlinda Vilanueva</div>
            <div class="contact-phone">
                <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                0912-345-6789
            </div>
        </div>

    </div>
</div>

<!-- ===== VIEW ANNOUNCEMENT MODAL ===== -->
<div class="modal fade ann-modal" id="viewAnnouncementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" style="max-width: 650px;">
        <div class="modal-content">
            <div class="modal-body">

                <div class="ann-modal-back">
                    <button type="button" data-bs-dismiss="modal">Back</button>
                </div>

                <div class="ann-modal-title" id="modalTitle">Christmas Party Meeting</div>
                <div class="ann-modal-meta">
                    <span id="modalDate">July 24, 2026</span>
                    <span class="ann-modal-divider">|</span>
                    <span id="modalTime">4:56 PM</span>
                </div>

                <div class="ann-modal-text" id="modalBody">
                    Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Javascript to dynamically populate the modal -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const viewModal = document.getElementById('viewAnnouncementModal');

        viewModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;

            const title = button.getAttribute('data-title');
            const date = button.getAttribute('data-date');
            const time = button.getAttribute('data-time');
            const body = button.getAttribute('data-body');

            const modalTitle = viewModal.querySelector('#modalTitle');
            const modalDate = viewModal.querySelector('#modalDate');
            const modalTime = viewModal.querySelector('#modalTime');
            const modalBody = viewModal.querySelector('#modalBody');

            modalTitle.textContent = title;
            modalDate.textContent = date;
            modalTime.textContent = time;
            modalBody.innerHTML = body.replace(/\n/g, '<br>');
        });
    });
</script>

@endsection
