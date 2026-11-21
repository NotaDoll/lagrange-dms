@extends('layouts.proprietor')

@section('title', $tenant->name)

@use('App\Models\Tenant')
@use('App\Rules\PhilippineMobile')

@section('content')
    @php
        $phone = fn ($number) => $number ? (PhilippineMobile::normalize($number) ?? $number) : null;
        $room = $tenant->currentAssignment?->bed;
        $statusClass = ['active' => 'text-bg-success', 'reserved' => 'text-bg-info', 'inactive' => 'text-bg-secondary'][$tenant->status] ?? 'text-bg-secondary';
        $riskClass = ['high' => 'text-bg-danger', 'medium' => 'text-bg-warning', 'low' => 'text-bg-success'];
        $paymentBadge = ['paid' => 'text-bg-success', 'overdue' => 'text-bg-danger', 'pending' => 'text-bg-secondary'];
    @endphp

    <x-breadcrumbs :items="['Tenants' => route('proprietor.tenants.index'), $tenant->name => null]" />

    @foreach (['success' => 'success', 'notice' => 'info'] as $key => $style)
        @if (session($key))
            <div class="alert alert-{{ $style }}" role="status">{{ session($key) }}</div>
        @endif
    @endforeach

    {{-- Header: who, where, and what you can do --}}
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $tenant->name }}</h1>
            <div class="d-flex flex-wrap align-items-center gap-2 text-muted small">
                <span class="badge {{ $statusClass }}">{{ Tenant::STATUS_LABELS[$tenant->status] ?? $tenant->status }}</span>
                <span><i class="bi bi-door-open" aria-hidden="true"></i> {{ $room ? $room->locationLabel() : 'No room assigned' }}</span>
                @if ($tenant->actualMoveInDate())
                    <span>· Moved in {{ $tenant->actualMoveInDate()->format('M j, Y') }}</span>
                @endif
                @if (! $tenant->user_id)
                    <span>· No login account</span>
                @endif
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            @if ($tenant->hasMovedIn())
                <a href="{{ route('proprietor.payments.create', ['tenant_id' => $tenant->id]) }}" class="btn btn-primary"><i class="bi bi-cash-coin" aria-hidden="true"></i> Record payment</a>
            @endif
            <div class="dropdown">
                <button class="btn btn-outline-secondary px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More actions for {{ $tenant->name }}" id="tenant-actions">
                    <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="tenant-actions">
                    <li><a class="dropdown-item" href="{{ route('proprietor.tenants.edit', $tenant) }}"><i class="bi bi-pencil me-2" aria-hidden="true"></i>Edit details</a></li>
                    @if ($tenant->hasMovedIn())
                        <li><a class="dropdown-item" href="{{ route('proprietor.payments.create', ['tenant_id' => $tenant->id]) }}"><i class="bi bi-cash-coin me-2" aria-hidden="true"></i>Record payment</a></li>
                    @endif
                    <li><a class="dropdown-item" href="{{ route('proprietor.payments.index', ['tenant_id' => $tenant->id]) }}"><i class="bi bi-journal-text me-2" aria-hidden="true"></i>Open in Payment Ledger</a></li>
                    <li><a class="dropdown-item" href="{{ route('proprietor.summer-storage.index') }}"><i class="bi bi-box-seam me-2" aria-hidden="true"></i>Summer / storage</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('proprietor.tenants.destroy', $tenant) }}"
                              onsubmit="return confirm('Delete {{ addslashes($tenant->name) }} and their login account? Their payment records are deleted too. This cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2" aria-hidden="true"></i>Delete tenant</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Summary --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body">
            <div class="text-muted small">Balance owed</div>
            <div class="fs-4 fw-semibold {{ $summary['outstanding_balance'] > 0 ? 'text-danger' : '' }}">₱{{ number_format($summary['outstanding_balance'], 2) }}</div>
            <div class="small text-muted">{{ $summary['unpaid_months'] > 0 ? $summary['unpaid_months'].' '.Str::plural('month', $summary['unpaid_months']).' with unpaid charges' : 'Nothing overdue' }}</div>
        </div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body">
            <div class="text-muted small">Total paid</div>
            <div class="fs-4 fw-semibold">₱{{ number_format($summary['total_paid'], 2) }}</div>
            <div class="small text-muted">{{ $summary['paid_count'] }} {{ Str::plural('payment', $summary['paid_count']) }} recorded</div>
        </div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body">
            <div class="text-muted small">{{ $rent['label'] ?? 'Monthly rent' }}</div>
            @if ($rent)
                <div class="fs-4 fw-semibold">₱{{ number_format($rent['monthly_rate'], 2) }}</div>
                <div class="small text-muted">Due every {{ $rent['due_date']?->format('jS') }} of the month</div>
            @else
                <div class="fs-6 text-muted mt-2">{{ $tenant->hasMovedIn() ? 'No room assigned' : 'Starts after move-in' }}</div>
            @endif
        </div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body">
            <div class="text-muted small">Payment risk</div>
            @if ($risk)
                <div class="mt-1"><span class="badge {{ $riskClass[$risk['risk_level']] }} fs-6">{{ ucfirst($risk['risk_level']) }}</span></div>
                @if ($riskReasons)
                    <ul class="small text-muted ps-3 mb-0 mt-2">@foreach ($riskReasons as $reason)<li>{{ $reason }}</li>@endforeach</ul>
                @else
                    <div class="small text-muted mt-2">No warning signs.</div>
                @endif
            @else
                <div class="fs-6 text-muted mt-2">Not billed yet</div>
            @endif
        </div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8 d-flex flex-column gap-4">
            {{-- Payment history --}}
            <section class="card" aria-labelledby="tenant-payments">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
                        <h2 id="tenant-payments" class="h6 fw-semibold mb-0">Payment history</h2>
                        <a class="small" href="{{ route('proprietor.payments.index', ['tenant_id' => $tenant->id]) }}">Open in Payment Ledger</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th scope="col">Description</th><th scope="col" class="text-end">Amount</th><th scope="col">Due</th><th scope="col">Paid</th><th scope="col">Status</th><th scope="col">Receipt</th></tr></thead>
                            <tbody>
                                @forelse ($history as $entry)
                                    <tr>
                                        <td>{{ $entry->description }}</td>
                                        <td class="text-end text-nowrap">₱{{ number_format((float) $entry->amount, 2) }}</td>
                                        <td class="text-nowrap">{{ $entry->due_date->format('M j, Y') }}</td>
                                        <td class="text-nowrap">{{ $entry->paid_date?->format('M j, Y') ?? '—' }}</td>
                                        <td>
                                            @if ($entry->is_advance_credit)
                                                <span class="badge text-bg-success">Paid</span><div class="small text-muted">From advance</div>
                                            @else
                                                <span class="badge {{ $paymentBadge[$entry->current_status] }}">{{ $entry->status_label }}</span>
                                                @if ($entry->current_status === 'overdue')<div class="small text-muted">{{ $entry->current_days_overdue }} {{ Str::plural('day', $entry->current_days_overdue) }} late</div>
                                                @elseif ($entry->payment_type)<div class="small text-muted">{{ \App\Models\Payment::PAYMENT_TYPE_LABELS[$entry->payment_type] ?? '' }}@if ($entry->days_overdue > 0) · {{ $entry->days_overdue }} {{ Str::plural('day', $entry->days_overdue) }} late @endif</div>@endif
                                            @endif
                                        </td>
                                        <td class="small font-monospace">{{ $entry->receipt_number ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">No charges or payments yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            {{-- Complaints --}}
            <section class="card" aria-labelledby="tenant-complaints">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
                        <h2 id="tenant-complaints" class="h6 fw-semibold mb-0">Complaints</h2>
                        <a class="small" href="{{ route('proprietor.complaints.index') }}">All complaints</a>
                    </div>
                    @forelse ($tenant->complaints as $complaint)
                        <div class="d-flex justify-content-between gap-3 py-2 {{ $loop->first ? '' : 'border-top' }}">
                            <div class="min-w-0">
                                <div class="small fw-semibold">{{ Str::headline($complaint->category) }} <span class="text-muted fw-normal">· {{ $complaint->created_at->format('M j, Y') }}</span></div>
                                <div class="small text-muted text-truncate" style="max-width: 36rem;">{{ $complaint->description }}</div>
                            </div>
                            <span class="badge align-self-start {{ $complaint->status === \App\Models\Complaint::STATUS_RESOLVED ? 'text-bg-success' : 'text-bg-warning' }}">{{ \App\Models\Complaint::STATUS_LABELS[$complaint->status] ?? Str::headline($complaint->status) }}</span>
                        </div>
                    @empty
                        <p class="small text-muted mb-0">No complaints from this tenant.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="col-xl-4 d-flex flex-column gap-4">
            {{-- Details --}}
            <section class="card" aria-labelledby="tenant-details">
                <div class="card-body">
                    <h2 id="tenant-details" class="h6 fw-semibold mb-3">Details</h2>
                    <dl class="row small mb-0 tenant-dl">
                        <dt class="col-5">Email</dt><dd class="col-7 text-break">{{ $tenant->email ?? '—' }}</dd>
                        <dt class="col-5">Mobile</dt><dd class="col-7">{{ $phone($tenant->contact_number) ?? 'Not provided' }}</dd>
                        <dt class="col-5">Home address</dt><dd class="col-7 text-break" style="white-space: pre-line">{{ $tenant->home_address ?? 'Not provided' }}</dd>
                        <dt class="col-5">Security deposit</dt><dd class="col-7">₱{{ number_format((float) $tenant->security_deposit, 2) }}</dd>
                        <dt class="col-5">Advance payment</dt><dd class="col-7">₱{{ number_format((float) $tenant->advance_payment, 2) }}</dd>
                        <dt class="col-5">Privacy consent</dt>
                        <dd class="col-7">
                            @if ($tenant->data_privacy_consent_at)
                                {{ $tenant->data_privacy_consent_at->format('M j, Y') }}
                                @if ($tenant->consentRecorder)<div class="text-muted">by {{ $tenant->consentRecorder->name }}</div>@endif
                            @else
                                Not recorded
                            @endif
                        </dd>
                    </dl>
                </div>
            </section>

            <section class="card" aria-labelledby="tenant-contacts">
                <div class="card-body">
                    <h2 id="tenant-contacts" class="h6 fw-semibold mb-3">Guardian</h2>
                    <dl class="row small mb-0 tenant-dl">
                        <dt class="col-5">Guardian</dt>
                        <dd class="col-7">
                            {{ $tenant->guardian_name ?? 'Not provided' }}
                            @if ($tenant->guardian_relationship)<div class="text-muted">{{ Tenant::GUARDIAN_RELATIONSHIPS[$tenant->guardian_relationship] ?? '' }}</div>@endif
                            @if ($tenant->guardian_contact_number)<div>{{ $phone($tenant->guardian_contact_number) }}</div>@else<div class="text-muted">No number (no SMS reminders)</div>@endif
                        </dd>
                    </dl>
                </div>
            </section>

            {{-- Guardian SMS reminders --}}
            <section class="card" aria-labelledby="tenant-sms">
                <div class="card-body">
                    <h2 id="tenant-sms" class="h6 fw-semibold mb-2">SMS reminders to guardian</h2>
                    @forelse ($tenant->smsLogs as $sms)
                        <div class="py-2 {{ $loop->first ? '' : 'border-top' }} small">
                            <div class="d-flex justify-content-between gap-2">
                                <span>{{ ($sms->sent_at ?? $sms->created_at)->format('M j, Y g:i A') }}</span>
                                <span class="badge {{ in_array($sms->status, ['sent', 'delivered'], true) ? 'text-bg-success' : ($sms->status === 'failed' ? 'text-bg-danger' : 'text-bg-secondary') }}">{{ ucfirst($sms->status) }}</span>
                            </div>
                            <div class="text-muted text-truncate" title="{{ $sms->message }}">{{ $sms->message }}</div>
                            @if ($sms->status === 'failed' && $sms->error_message)<div class="text-danger">{{ $sms->error_message }}</div>@endif
                        </div>
                    @empty
                        <p class="small text-muted mb-0">No reminders sent. High-risk tenants' guardians can be texted from the Dashboard.</p>
                    @endforelse
                </div>
            </section>

            {{-- Room history --}}
            <section class="card" aria-labelledby="tenant-rooms">
                <div class="card-body">
                    <h2 id="tenant-rooms" class="h6 fw-semibold mb-2">Room history</h2>
                    @forelse ($roomHistory as $assignment)
                        <div class="d-flex justify-content-between gap-2 py-2 small {{ $loop->first ? '' : 'border-top' }}">
                            <span>{{ $assignment->bed?->locationLabel() ?? 'Removed bed' }}</span>
                            <span class="text-muted text-nowrap">{{ $assignment->start_date->format('M j, Y') }} – {{ $assignment->end_date?->format('M j, Y') ?? 'now' }}</span>
                        </div>
                    @empty
                        <p class="small text-muted mb-0">No room assigned yet.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    <style>
        .tenant-dl dt { font-weight: 500; color: var(--theme-text-muted, #6c757d); }
        .tenant-dl dd { margin-bottom: .6rem; }
        .min-w-0 { min-width: 0; }
    </style>
@endsection
