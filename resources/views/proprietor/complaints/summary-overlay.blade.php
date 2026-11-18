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
                    <div class="complaint-detail-description text-break" id="anSummaryText"></div>

                    <details class="mt-3 small" id="anSummaryIncluded">
                        <summary>View included complaints</summary>
                        <ul class="mt-2 mb-1" id="anSummaryComplaints"></ul>
                        <p class="text-muted mb-0" id="anSummaryMissing" hidden>Some complaints are no longer available.</p>
                    </details>
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
