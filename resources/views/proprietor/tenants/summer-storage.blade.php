@extends('layouts.proprietor')

@section('title', 'Summer / Storage')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Summer / Storage</h1>
        <a href="{{ route('proprietor.tenants.index') }}" class="btn btn-outline-secondary">Back to tenants</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if ($result = session('storage_result'))
        <div class="alert alert-success" role="status">
            {{ $result['updated'] }} tenant-month {{ Str::plural('selection', $result['updated']) }} saved for {{ $result['months'] }}.
        </div>
        @if ($result['warnings'])
            <div class="alert alert-warning" role="alert">
                <ul class="mb-0">
                    @foreach ($result['warnings'] as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif

    <form method="GET" action="{{ route('proprietor.summer-storage.index') }}" class="card mb-3">
        <div class="card-body d-flex flex-wrap gap-3 align-items-end">
            <div>
                <label for="storage-year" class="form-label">Year</label>
                <input id="storage-year" name="year" type="number" min="1900" max="9999" value="{{ $year }}" class="form-control" required>
            </div>
            <fieldset>
                <legend class="form-label">Months</legend>
                <div class="d-flex flex-wrap gap-2">
                    @foreach (range(1, 12) as $number)
                        <label class="form-check-label">
                            <input type="checkbox" name="months[]" value="{{ $number }}" class="form-check-input"
                                   @checked(in_array($number, $months->pluck('month')->all(), true))>
                            {{ \Illuminate\Support\Carbon::create(2000, $number, 1)->format('M') }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <button type="submit" class="btn btn-outline-primary">Show months</button>
        </div>
    </form>

    <div class="card">
        <div class="card-body">
            <form id="storage-bulk" method="POST" action="{{ route('proprietor.summer-storage.store') }}" data-months="{{ $months->map(fn ($month) => $month->format('F Y'))->implode(', ') }}">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                @foreach ($months as $month)
                    <input type="hidden" name="months[]" value="{{ $month->month }}">
                @endforeach
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <label for="storage-select-all" class="form-check-label me-2">
                        <input id="storage-select-all" type="checkbox" class="form-check-input mt-0 me-1"> Select all
                    </label>
                    <button type="submit" name="action" value="storage" class="btn btn-primary btn-sm">Mark as Storage</button>
                    <button type="submit" name="action" value="regular" class="btn btn-outline-primary btn-sm">Mark as Regular</button>
                    <span class="small text-muted" id="storage-selected" aria-live="polite"></span>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th scope="col"><span class="visually-hidden">Select tenant</span></th>
                            <th scope="col">Tenant</th>
                            @foreach ($months as $month)
                                <th scope="col">{{ $month->format('F Y') }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tenants as $tenant)
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input mt-0" form="storage-bulk" name="tenants[]" value="{{ $tenant->id }}"
                                           aria-label="Select {{ $tenant->name }}" data-select-tenant>
                                </td>
                                <th scope="row">{{ $tenant->name }}</th>
                                @foreach ($months as $month)
                                    @php($mode = $tenant->monthModes->first(fn ($item) => $item->month->equalTo($month)))
                                    <td>
                                        @if ($mode?->mode === \App\Models\TenantMonthMode::STORAGE)
                                            <span class="badge text-bg-primary">Storage</span>
                                            <span class="small">₱{{ number_format($mode->storageFee(), 2) }}</span>
                                            <details class="mt-1">
                                                <summary class="small text-primary" aria-label="Edit storage fee for {{ $tenant->name }}, {{ $month->format('F Y') }}">Edit amount</summary>
                                                <form method="POST" action="{{ route('proprietor.summer-storage.store') }}" class="d-flex align-items-end gap-2 mt-2">
                                                    @csrf
                                                    <input type="hidden" name="action" value="amount">
                                                    <input type="hidden" name="year" value="{{ $year }}">
                                                    <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                                                    <input type="hidden" name="amount_month" value="{{ $month->month }}">
                                                    @foreach ($months as $selectedMonth)
                                                        <input type="hidden" name="months[]" value="{{ $selectedMonth->month }}">
                                                    @endforeach
                                                    <div>
                                                        <label for="fee-{{ $tenant->id }}-{{ $month->month }}" class="form-label small">Fee (₱)</label>
                                                        <input type="number" id="fee-{{ $tenant->id }}-{{ $month->month }}" name="amount" min="0" max="99999999.99" step="0.01"
                                                               class="form-control form-control-sm" placeholder="{{ config('dorm.storage_fee') }}" value="{{ $mode->amount }}"
                                                               aria-describedby="fee-help-{{ $tenant->id }}-{{ $month->month }}">
                                                        <small id="fee-help-{{ $tenant->id }}-{{ $month->month }}" class="text-muted">Blank uses the default.</small>
                                                    </div>
                                                    <button type="submit" class="btn btn-outline-primary btn-sm align-self-center">Save</button>
                                                </form>
                                            </details>
                                        @else
                                            <span class="badge text-bg-secondary">Regular</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ $months->count() + 2 }}" class="text-center text-muted py-4">No active tenants.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const form = document.getElementById('storage-bulk');
        const all = document.getElementById('storage-select-all');
        const selected = document.getElementById('storage-selected');
        const boxes = [...document.querySelectorAll('[data-select-tenant]')];
        const buttons = [...form.querySelectorAll('button[type="submit"]')];
        const countSelected = () => boxes.filter(box => box.checked).length;

        function syncSelection() {
            const count = countSelected();
            selected.textContent = count + ' selected';
            all.checked = count > 0 && count === boxes.length;
            all.indeterminate = count > 0 && count < boxes.length;
            all.disabled = boxes.length === 0;
            buttons.forEach(button => { button.disabled = count === 0; });
        }

        all.addEventListener('change', () => {
            boxes.forEach(box => { box.checked = all.checked; });
            syncSelection();
        });
        boxes.forEach(box => box.addEventListener('change', syncSelection));
        form.addEventListener('submit', event => {
            const count = countSelected();
            const mode = event.submitter?.value === 'regular' ? 'Regular' : 'Storage';
            if (count === 0 || !window.confirm('Mark ' + count + ' tenant(s) as ' + mode + ' for ' + form.dataset.months + '?')) {
                event.preventDefault();
            }
        });
        syncSelection();
    })();
</script>
@endpush
