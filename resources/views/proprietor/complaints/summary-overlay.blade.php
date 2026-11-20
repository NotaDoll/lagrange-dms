<div class="modal fade complaint-detail-modal" id="anSummaryOverlay" tabindex="-1"
    role="dialog" aria-modal="true" aria-labelledby="anSummaryTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="anSummaryTitle" tabindex="-1">Complaints Summary</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div data-summary-panel="loading" role="status" hidden>
                    <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span><span id="anSummaryLoading"></span>
                </div>

                <div data-summary-panel="error" hidden>
                    <p id="anSummaryError" class="text-danger mb-0" role="alert"></p>
                </div>

                <div data-summary-panel="detail" hidden>
                    <p class="fw-semibold mb-2" id="anSummaryCount"></p>
                    <div class="d-flex flex-wrap gap-1 mb-2" id="anSummaryChips"></div>
                    <p class="text-muted small mb-3" id="anSummaryGenerated"></p>
                    <div class="p-3 rounded mb-3" id="anSummaryUrgent" style="background-color: #fff5f5; border: 1px solid #fed7d7;" hidden>
                        <h3 class="h6 fw-bold text-danger mb-1"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Most urgent</h3>
                        <p class="mb-0 small" id="anSummaryUrgentText"></p>
                    </div>
                    {{-- Older summaries without issue groups show their saved text instead. --}}
                    <div class="complaint-detail-description text-break" id="anSummaryText" hidden></div>
                    <div id="anSummaryThemes" hidden>
                        <p class="small text-muted mb-2">Sorted by what each complaint actually says. Click an issue to see its complaints.</p>
                        <div class="d-flex flex-column gap-3" id="anSummaryThemeLinks"></div>
                    </div>
                    <div class="alert alert-warning small mt-3 mb-0 py-2" id="anSummaryRecategorized" hidden>
                        <strong id="anSummaryRecategorizedTitle"></strong>
                        <ul class="mb-0 mt-1 ps-3" id="anSummaryRecategorizedList"></ul>
                    </div>
                    <a class="btn btn-sm btn-outline-primary mt-3" id="anSummaryAllComplaints" hidden>
                        <i class="bi bi-list-ul" aria-hidden="true"></i> View all complaints in this summary
                    </a>

                    <p class="text-muted small mt-3 mb-0" id="anSummaryMissing" hidden>Some complaints in this summary are no longer available.</p>
                </div>

                <div data-summary-panel="history" hidden>
                    <p class="text-muted mb-0" id="anSummaryEmpty" hidden>No summaries yet.</p>
                    <ul class="list-group list-group-flush" id="anHistoryItems" aria-label="Saved complaint summaries"></ul>
                    <nav class="d-flex flex-wrap align-items-center gap-2 mt-3" id="anHistoryPages" aria-label="Summary history pages">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="anHistoryPrevious"><i class="bi bi-chevron-left" aria-hidden="true"></i> Previous</button>
                        <span class="small text-muted" id="anHistoryPage" aria-live="polite"></span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="anHistoryNext">Next <i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                    </nav>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="anSummaryBack" hidden><i class="bi bi-arrow-left" aria-hidden="true"></i> Back</button>
                <button type="button" class="btn btn-outline-primary" id="anSummaryRetry" hidden><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Try again</button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal"><i class="bi bi-check-lg" aria-hidden="true"></i> OK</button>
            </div>
        </div>
    </div>
</div>
