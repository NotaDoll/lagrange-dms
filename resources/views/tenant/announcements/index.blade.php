@extends('layouts.tenant')

@section('title', 'Announcements')

@section('content')

<style>
    :root {
        --ann-plum: #5E1049;
        --ann-plum-hover: #4A0D39;
        --ann-pink-light: #FDF2F8;
        --ann-pink-card: #FFF5F9;
        --ann-border: #E8DEE5;
        --ann-red: #B80000;
        --ann-text-muted: #6b7280;
    }

    /* ===== MAIN PAGE LAYOUT ===== */
    .ann-page-wrapper {
        display: flex;
        gap: 30px;
        flex-wrap: wrap;
    }

    .ann-left-col {
        flex: 2;
        min-width: 300px;
    }

    .ann-right-col {
        flex: 1;
        min-width: 280px;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* ===== HEADER & CONTROLS ===== */
    .ann-header h1 {
        font-size: 1.6rem;
        font-weight: 700;
        color: #1a1a1a;
        margin-bottom: 4px;
    }
    .ann-header p {
        color: #6b7280;
        font-size: 0.9rem;
        margin-bottom: 20px;
    }

    .ann-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 25px;
    }

    .ann-tabs {
        display: flex;
        gap: 20px;
        font-weight: 500;
        font-size: 0.95rem;
    }
    .ann-tabs a {
        color: #9ca3af;
        text-decoration: none;
        transition: 0.2s;
    }
    .ann-tabs a.active {
        color: #1a1a1a;
        font-weight: 600;
    }
    .ann-tabs a:hover { color: #4b5563; }

    .ann-actions {
        display: flex;
        gap: 12px;
    }

    /* Pill Buttons */
    .ann-btn-outline {
        background: transparent;
        border: 1px solid #d1d5db;
        color: #374151;
        border-radius: 999px;
        padding: 8px 18px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: 0.2s;
        cursor: pointer;
    }
    .ann-btn-outline:hover { background: #f9fafb; border-color: #9ca3af; }

    .ann-btn-solid {
        background: var(--ann-plum);
        border: none;
        color: #fff;
        border-radius: 999px;
        padding: 8px 18px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: 0.2s;
        border: 1px solid var(--ann-plum);
        cursor: pointer;
    }
    .ann-btn-solid:hover { background: var(--ann-plum-hover); color: #fff; }

    /* ===== ANNOUNCEMENT LIST ===== */
    .ann-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .ann-item {
        background: var(--ann-pink-card);
        border: 1px solid #f3e8f0;
        border-radius: 8px;
        padding: 20px 24px;
        transition: 0.2s;
    }
    .ann-item:hover { border-color: var(--ann-border); }

    .ann-item-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 8px;
    }
    .ann-item-title {
        font-weight: 600;
        font-size: 1rem;
        color: var(--ann-plum);
        margin: 0;
    }
    .ann-item-date {
        font-size: 0.75rem;
        color: #9ca3af;
        white-space: nowrap;
    }

    .ann-item-body {
        font-size: 0.9rem;
        color: #4b5563;
        line-height: 1.5;
        margin-bottom: 10px;
    }

    .ann-item-footer {
        display: flex;
        justify-content: flex-end;
    }
    .ann-read-more {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--ann-plum);
        text-decoration: none;
        cursor: pointer;
        background: none;
        border: none;
    }
    .ann-read-more:hover { text-decoration: underline; }

    /* ===== PAGINATION ===== */
    .ann-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 30px;
        font-size: 0.85rem;
        color: #6b7280;
    }
    .ann-pager { display: flex; align-items: center; gap: 8px; }
    .ann-pager-btn {
        width: 28px; height: 28px; border-radius: 50%;
        border: 1px solid #d1d5db; background: #fff;
        display: flex; align-items: center; justify-content: center;
        color: #6b7280; text-decoration: none; font-size: 0.8rem; transition: 0.2s;
    }
    .ann-pager-btn:hover:not(.disabled) { border-color: var(--ann-plum); color: var(--ann-plum); }
    .ann-pager-btn.disabled { opacity: 0.5; pointer-events: none; }
    .ann-pager-current {
        width: 28px; height: 28px; border-radius: 50%;
        background: var(--ann-plum); color: #fff; font-weight: 600;
        display: flex; align-items: center; justify-content: center; font-size: 0.8rem;
    }

    /* ===== RIGHT SIDEBAR ===== */
    .ann-sidebar-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 20px;
    }
    .ann-sidebar-title {
        font-weight: 600;
        font-size: 0.95rem;
        color: #1a1a1a;
        margin-bottom: 15px;
    }

    .ann-emergency-box {
        background: var(--ann-red);
        border-radius: 12px;
        padding: 20px;
        color: #fff;
    }
    .ann-emergency-box h5 {
        font-weight: 700;
        font-size: 0.95rem;
        margin-bottom: 4px;
    }
    .ann-emergency-box p {
        font-size: 0.8rem;
        opacity: 0.9;
        margin-bottom: 15px;
    }
    .ann-emergency-box .contact-person {
        font-size: 0.85rem;
    }
    .ann-emergency-box .contact-phone {
        font-size: 1rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .ann-emergency-box .contact-phone svg {
        width: 16px; height: 16px; fill: currentColor;
    }

    /* ===== FILTER DROPDOWN ===== */
    .ann-filter-dropdown {
        min-width: 320px;
        padding: 20px 24px;
        border-radius: 16px;
        background: #fff;
        border: 1px solid #e5e7eb;
        box-shadow: 0 10px 25px rgba(0,0,0,0.06);
        margin-top: 12px !important;
    }

    .ann-filter-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    .ann-filter-header h6 {
        font-weight: 700;
        font-size: 1.1rem;
        margin: 0;
        color: #000;
    }
    .ann-filter-clear {
        font-size: 0.8rem;
        color: #3b82f6;
        text-decoration: none;
        font-weight: 500;
    }

    .ann-filter-section-title {
        font-size: 0.75rem;
        font-weight: 700;
        color: #a855a7;
        text-transform: uppercase;
        margin-bottom: 10px;
    }

    .ann-filter-dropdown .form-check-label {
        font-weight: 500;
        color: #333;
        font-size: 0.85rem;
    }
    .ann-filter-dropdown .form-check-input {
        border-color: #d1d5db;
    }
    .ann-filter-dropdown .form-check-input:checked {
        background-color: var(--ann-plum);
        border-color: var(--ann-plum);
    }

    .ann-filter-dropdown .form-select {
        border-radius: 4px;
        padding: 8px 12px;
        font-weight: 500;
        font-size: 0.9rem;
        border: 1px solid #d1d5db;
    }

    .ann-filter-apply-btn {
        background: var(--ann-plum);
        color: white;
        border-radius: 999px;
        padding: 10px;
        font-weight: 600;
        border: none;
        width: 100%;
        margin-top: 15px;
        transition: 0.2s;
    }
    .ann-filter-apply-btn:hover { background: var(--ann-plum-hover); color: white; }

    /* ===== VIEW ANNOUNCEMENT MODAL ===== */
    .ann-modal .modal-content {
        border-radius: 4px; border: none; box-shadow: 0 15px 40px rgba(0,0,0,0.1); padding: 30px 40px;
    }
    .ann-modal .modal-body { padding: 0; }

    .ann-modal-back {
        display: flex; justify-content: flex-end; margin-bottom: 15px;
    }
    .ann-modal-back button {
        background: none; border: none; color: #4b5563; font-weight: 500; font-size: 0.85rem;
    }

    .ann-modal-title {
        font-size: 1.6rem; font-weight: 700; color: var(--ann-plum); margin-bottom: 6px;
    }
    .ann-modal-meta {
        font-size: 0.85rem; color: #6b7280; margin-bottom: 25px; display: flex; align-items: center; gap: 8px;
    }
    .ann-modal-meta span { font-weight: 500; }
    .ann-modal-divider { color: #9ca3af; }

    .ann-modal-text {
        font-size: 0.95rem; color: #374151; line-height: 1.7; margin-bottom: 20px;
    }
    .ann-modal-list {
        padding-left: 20px; margin-bottom: 30px; font-size: 0.95rem; color: #374151; line-height: 1.7;
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 900px) {
        .ann-page-wrapper { flex-direction: column-reverse; }
        .ann-right-col { flex-direction: row; flex-wrap: wrap; }
        .ann-right-col > * { flex: 1; min-width: 200px; }
    }
    @media (max-width: 600px) {
        .ann-controls { flex-direction: column; align-items: stretch; gap: 10px; }
        .ann-actions { justify-content: flex-start; }
        .ann-tabs { justify-content: flex-start; }
        .ann-filter-dropdown { min-width: unset; width: 100%; }
    }
</style>

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
