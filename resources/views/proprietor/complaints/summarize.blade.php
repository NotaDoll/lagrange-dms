@extends('layouts.proprietor')

@section('title', 'Summarize Complaints')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Summarize Complaints</h1>
            <p class="text-muted mb-0">Generate an AI-driven overview of current resident issues.</p>
        </div>
        <a href="{{ route('proprietor.complaints.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <div class="row g-3 align-items-end">
                {{-- Category Filter --}}
                <div class="col-md-4">
                    <label for="category-filter" class="form-label fw-semibold text-muted small">Category</label>
                    <select class="form-select" id="category-filter">
                        <option value="">All Categories</option>
                        @foreach (['maintenance' => 'Maintenance', 'noise' => 'Noise', 'billing' => 'Billing', 'other' => 'Other'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Date From Filter --}}
                <div class="col-md-3">
                    <label for="date-from" class="form-label fw-semibold text-muted small">Date From</label>
                    <input type="date" class="form-control" id="date-from">
                </div>

                {{-- Date To Filter --}}
                <div class="col-md-3">
                    <label for="date-to" class="form-label fw-semibold text-muted small">Date To</label>
                    <input type="date" class="form-control" id="date-to">
                </div>

                {{-- Submit Button --}}
                <div class="col-md-2">
                    <button type="button" id="generate-summary" class="btn btn-primary w-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-stars me-1" viewBox="0 0 16 16">
                            <path d="M7.657 6.247c.11-.33.576-.33.686 0l.645 1.937a2.89 2.89 0 0 0 1.829 1.828l1.936.645c.33.11.33.576 0 .686l-1.937.645a2.89 2.89 0 0 0-1.828 1.829l-.645 1.936a.361.361 0 0 1-.686 0l-.645-1.937a2.89 2.89 0 0 0-1.828-1.828l-1.937-.645a.361.361 0 0 1 0-.686l1.937-.645a2.89 2.89 0 0 0 1.828-1.828l.645-1.937zM3.794 1.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387A1.734 1.734 0 0 0 4.593 5.69l-.387 1.162a.217.217 0 0 1-.412 0L3.407 5.69A1.734 1.734 0 0 0 2.31 4.593l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387A1.734 1.734 0 0 0 3.407 2.31l.387-1.162zM10.863.099a.145.145 0 0 1 .274 0l.258.774c.115.346.386.617.732.732l.774.258a.145.145 0 0 1 0 .274l-.774.258a1.156 1.156 0 0 0-.732.732l-.258.774a.145.145 0 0 1-.274 0l-.258-.774a1.156 1.156 0 0 0-.732-.732L9.1 2.137a.145.145 0 0 1 0-.274l.774-.258c.346-.115.617-.386.732-.732L10.863.1z"/>
                        </svg>
                        Generate
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="summary-container" class="d-none">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">

                {{-- Header --}}
                <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
                    <h2 class="h5 fw-bold mb-0">AI Complaint Breakdown</h2>
                    <span class="badge bg-light text-dark border"><i class="bi bi-robot me-1"></i> AI-Generated</span>
                </div>

                <div class="row g-4">
                    {{-- Left: Critical Action --}}
                    <div class="col-lg-4">
                        <div id="critical-action-box" class="d-none h-100 p-4 rounded" style="background-color: #fff5f5; border: 1px solid #fed7d7;">
                            <div class="d-flex align-items-center gap-2 mb-2 text-danger">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-exclamation-triangle-fill" viewBox="0 0 16 16">
                                    <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                                </svg>
                                <h3 class="h6 fw-bold mb-0">Priority Action</h3>
                            </div>
                            <p id="critical-action-text" class="mb-0 small text-dark fw-medium"></p>
                        </div>
                    </div>

                    {{-- Right: Top Issues --}}
                    <div class="col-lg-8">
                        <h3 class="h6 fw-bold text-muted mb-3 text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Top Issues by Category</h3>
                        <ul id="issues-list" class="list-unstyled mb-0 d-flex flex-column gap-3"></ul>
                    </div>
                </div>

                {{-- Footer & Raw Data --}}
                <div class="mt-5 pt-3 border-top">
                    <details class="text-muted small">
                        <summary class="text-primary fw-semibold" style="cursor: pointer;">View full raw AI text</summary>
                        <div id="raw-text-output" class="mt-3 p-3 bg-light rounded text-start" style="white-space: pre-wrap; font-family: monospace; font-size: 0.75rem; line-height: 1.5;"></div>
                    </details>
                    <div id="footer-stats" class="mt-3 text-muted small d-flex flex-wrap gap-4"></div>
                </div>

            </div>
        </div>
    </div>

    <div id="summary-error" class="alert alert-danger d-none mt-4"></div>
@endsection

@push('scripts')
<script>
document.getElementById('generate-summary').addEventListener('click', async () => {
    const btn = document.getElementById('generate-summary');
    const btnOriginalHtml = btn.innerHTML;

    // UI Elements
    const container = document.getElementById('summary-container');
    const errorBox = document.getElementById('summary-error');

    // Reset UI
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>...`;
    container.classList.add('d-none');
    errorBox.classList.add('d-none');

    try {
        const response = await fetch("{{ route('proprietor.ai-summaries.store') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                category: document.getElementById('category-filter').value || null,
                date_from: document.getElementById('date-from').value || null,
                date_to: document.getElementById('date-to').value || null,
            }),
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data.message ?? `Request failed (${response.status})`);
        }

        const text = data.summary_result ?? data.summary;
        if (!text) throw new Error('The server returned no summary text.');

        // --- TEXT PARSING LOGIC ---
        const lines = text.split('\n');
        let issues = [];
        let criticalAction = '';
        let footerStats = '';
        let rawText = text;

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

        // --- RENDER UI ---

        // 1. Render Critical Action
        const actionBox = document.getElementById('critical-action-box');
        if (criticalAction) {
            document.getElementById('critical-action-text').textContent = criticalAction;
            actionBox.classList.remove('d-none');
        } else {
            actionBox.classList.add('d-none');
        }

        // 2. Render Issues List
        const listEl = document.getElementById('issues-list');
        listEl.innerHTML = '';
        issues.forEach(issue => {
            let countBadge = issue.count ? `<span class="badge bg-secondary ms-2 rounded-pill">${issue.count}</span>` : '';
            listEl.innerHTML += `
                <li class="d-flex align-items-start gap-2">
                    <div class="mt-1 text-primary" style="font-size: 1.2rem; line-height: 1;">
                        &bull;
                    </div>
                    <div>
                        <span class="text-dark fw-medium">${issue.text}</span>
                        ${countBadge}
                    </div>
                </li>
            `;
        });

        // 3. Render Raw Text
        document.getElementById('raw-text-output').textContent = rawText;

        // 4. Render Footer
        document.getElementById('footer-stats').innerHTML = `<em>${footerStats}</em>`;

        // Show container
        container.classList.remove('d-none');

    } catch (err) {
        errorBox.textContent = err.message;
        errorBox.classList.remove('d-none');
    } finally {
        btn.disabled = false;
        btn.innerHTML = btnOriginalHtml;
    }
});
</script>
@endpush
