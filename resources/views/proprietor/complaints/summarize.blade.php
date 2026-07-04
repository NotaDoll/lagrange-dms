@extends('layouts.proprietor')

@section('title', 'Summarize Complaints')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Summarize Complaints</h1>
        <a href="{{ route('proprietor.complaints.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label for="category-filter" class="form-label">Category</label>
                    <select class="form-select" id="category-filter">
                        <option value="">All categories</option>
                        @foreach (['maintenance' => 'Maintenance', 'noise' => 'Noise', 'billing' => 'Billing', 'other' => 'Other'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <button type="button" id="generate-summary" class="btn btn-primary w-100">Generate Summary</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div id="summary-result" class="text-muted">No summary generated yet.</div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.getElementById('generate-summary').addEventListener('click', async () => {
    const btn = document.getElementById('generate-summary');
    const result = document.getElementById('summary-result');
    btn.disabled = true;
    btn.textContent = 'Generating…';

    const response = await fetch("{{ route('proprietor.ai-summaries.store') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({
            category: document.getElementById('category-filter').value,
        }),
    });

    const data = await response.json();
    result.innerHTML = `<div class="card"><div class="card-body">
        <p>${data.summary_result}</p>
        <small class="text-muted">AI-generated — please verify before acting.</small>
    </div></div>`;
    btn.disabled = false;
    btn.textContent = 'Generate Summary';
});
</script>
@endpush
