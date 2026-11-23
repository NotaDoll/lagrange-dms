                @php($textable = $followUps->filter(fn ($row) => $row->risk_level === 'high' && filled($row->tenant->guardian_contact_number))->count())
                {{-- Card header: title and subtitle on the left, actions on the right (same pattern as Trends). --}}
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h2 id="pd-follow" class="h6 fw-semibold mb-1">Tenants to follow up</h2>
                        <p class="pd-note mb-0">High risk requires overdue today, consecutive late months, and repeated late months. Medium means other warning signs · Sorted by risk, then amount owed</p>
                        <p class="pd-note mb-0">Monthly boxes show payment status; the badge shows current follow-up risk.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        {{-- Secondary action first, main action last (rightmost). --}}
                        @if (($showViewAll ?? false) && $followUps->isNotEmpty())
                            <a class="btn btn-sm btn-outline-danger" href="{{ route('proprietor.payments.at-risk') }}" title="See every tenant to follow up on one page"><i class="bi bi-shield-exclamation me-1" aria-hidden="true"></i> View all</a>
                        @endif
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('proprietor.settings.edit') }}#risk-thresholds" title="Change the rules for High and Medium risk (late months, grace period, days overdue)"><i class="bi bi-sliders me-1" aria-hidden="true"></i> Thresholds</a>
                        @if ($textable > 0)
                            <button type="button" class="btn btn-sm btn-primary" id="pd-sms-all" data-preview-url="{{ route('proprietor.dashboard.sms.bulk-preview') }}" data-url="{{ route('proprietor.dashboard.sms.bulk') }}" title="Text a payment reminder to the guardians of {{ $textable }} high-risk {{ Str::plural('tenant', $textable) }}. You can review and edit each message before sending."><i class="bi bi-chat-dots me-1" aria-hidden="true"></i> Send reminders ({{ $textable }})</button>
                        @endif
                    </div>
                </div>
                @if ($followUps->isNotEmpty())
                    {{-- Toolbar: risk filter on the left, search on the right. --}}
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3" id="pd-follow-filters">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Show risk level">
                            @php($filterHints = ['all' => 'Show every tenant to follow up', 'high' => 'Show tenants meeting all three warnings: overdue now, consecutive late months, and repeated late months', 'medium' => 'Show tenants with warning signs who do not meet all three High risk conditions'])
                            @foreach (['all' => 'All', 'high' => 'High', 'medium' => 'Medium'] as $value => $label)
                                <button type="button" class="btn {{ $value === 'all' ? 'btn-primary' : 'btn-outline-primary' }}" data-level="{{ $value }}" aria-pressed="{{ $value === 'all' ? 'true' : 'false' }}" title="{{ $filterHints[$value] }}">
                                    {{ $label }} <span class="badge text-bg-light ms-1">{{ $value === 'all' ? $followUps->count() : $followUps->where('risk_level', $value)->count() }}</span>
                                </button>
                            @endforeach
                        </div>
                        <label class="visually-hidden" for="pd-follow-search">Search tenants to follow up</label>
                        <input type="search" class="form-control form-control-sm pd-follow-search" id="pd-follow-search" placeholder="Search name or room" autocomplete="off" title="Type a tenant's name or room number">
                    </div>
                @endif
                <div id="pd-sms-message" class="d-none alert" role="status" aria-live="polite" data-refresh-url="{{ route('proprietor.dashboard.sms.refresh') }}"></div>
                <div class="table-responsive"><table class="table align-middle pd-follow-table" data-per-page="{{ $perPage ?? 5 }}">
                    <caption class="visually-hidden">Active tenants with High or Medium payment risk</caption>
                    <thead><tr><th scope="col">Tenant</th><th scope="col" class="text-end">Owes</th><th scope="col">Payment each month</th><th scope="col" class="text-end">Action</th></tr></thead>
                    <tbody>
                        @forelse ($followUps as $row)
                            {{-- The overdue balance shows under "Owes"; the line under the name gives the strongest other reason. --}}
                            @php($room = $row->tenant->currentAssignment?->bed?->room?->room_number)
                            @php($unpaidNow = \App\Services\PaymentRiskService::UNPAID_NOW)
                            @php($overdueNow = in_array($unpaidNow, $row->reasons, true))
                            @php($others = array_values(array_diff($row->reasons, [$unpaidNow])))
                            @php($mainReason = collect($others)->first(fn ($reason) => str_ends_with($reason, 'in a row')) ?? ($others[0] ?? ($overdueNow ? $unpaidNow : '')))
                            @php($billedMonths = $row->trend->filter(fn ($point) => $point['status'] !== null))
                            @php($paymentWords = ['on_time' => 'paid on time', 'late' => 'paid late', 'overdue' => 'overdue', 'pending' => 'not overdue yet'])
                            @php($trendLabel = 'Payment status each month: '.$billedMonths->map(fn ($point) => $point['label'].' '.$paymentWords[$point['status']])->implode(', '))
                            <tr data-level="{{ $row->risk_level }}" data-search="{{ Str::lower($row->tenant->name.' '.$room) }}">
                                <td>
                                    <div class="fw-semibold">{{ $row->tenant->name }}
                                        <span class="pd-tag pd-{{ $row->risk_level }} ms-1" tabindex="0" title="Why flagged:&#10;• {{ implode("\n• ", $row->reasons) }}">{{ ucfirst($row->risk_level) }}</span>
                                    </div>
                                    <div class="pd-note">{{ $room ? 'Room '.$room : 'No room' }}@if ($mainReason) · <span class="pd-main-reason">{{ $mainReason }}</span>@endif</div>
                                    <span class="visually-hidden">Why flagged: {{ implode('; ', $row->reasons) }}</span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="{{ $row->owes > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">₱{{ number_format($row->owes, 2) }}</div>
                                    @if ($overdueNow)<div class="pd-overdue">overdue now</div>@endif
                                </td>
                                <td>
                                    @if ($billedMonths->isNotEmpty())
                                        <div class="pd-history" role="img" aria-label="{{ $trendLabel }}" title="{{ $trendLabel }}">
                                            @foreach ($billedMonths as $point)
                                                <span class="pd-history-cell pd-payment-{{ $point['status'] }}"><span>{{ $point['label'] }}</span></span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="pd-note">Not billed yet</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex flex-column align-items-end gap-1">
                                        <div class="d-flex gap-1">
                                            @if ($row->risk_level === 'high' && filled($row->tenant->guardian_contact_number))
                                                @php($smsLabel = $row->sms_sent ? 'Send reminder again' : 'Send reminder')
                                                <button type="button" class="btn btn-sm btn-outline-primary pd-icon-btn pd-send-sms" title="{{ $smsLabel }}" aria-label="{{ $smsLabel }}"
                                                        data-url="{{ route('proprietor.dashboard.sms', $row->tenant) }}"
                                                        data-preview-url="{{ route('proprietor.dashboard.sms.preview', $row->tenant) }}"
                                                        data-log-id="{{ $row->sms_log_id }}"><i class="bi bi-chat-dots" aria-hidden="true"></i></button>
                                            @elseif ($row->risk_level === 'high')
                                                <button type="button" class="btn btn-sm btn-outline-secondary pd-icon-btn" disabled title="Guardian number missing" aria-label="Guardian number missing"><i class="bi bi-telephone-x" aria-hidden="true"></i></button>
                                            @endif
                                            <a class="btn btn-sm btn-outline-secondary pd-icon-btn" href="{{ route('proprietor.payments.history', $row->tenant) }}" title="Open ledger" aria-label="Open ledger for {{ $row->tenant->name }}"><i class="bi bi-journal-text" aria-hidden="true"></i></a>
                                        </div>
                                        <small class="text-muted pd-sms-status" data-tenant-id="{{ $row->tenant->id }}" aria-live="polite">{{ match ($row->sms_status) {
                                            'queued' => 'SMS queued',
                                            'unknown' => 'SMS unconfirmed',
                                            'delivered' => 'SMS delivered',
                                            'sent' => 'SMS sent',
                                            default => '',
                                        } }}</small>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-5"><i class="bi bi-check-circle text-success" aria-hidden="true"></i> No tenants need a payment follow-up right now.</td></tr>
                        @endforelse
                        @if ($followUps->isNotEmpty())
                            <tr class="d-none" id="pd-follow-none"><td colspan="4" class="text-center text-muted py-4">No tenants match this filter.</td></tr>
                        @endif
                    </tbody>
                </table></div>
                @if ($followUps->isNotEmpty())
                    <p class="pd-note mb-0">
                        Payment status:
                        <span class="pd-history-cell pd-payment-on_time pd-history-key ms-1"></span> Paid on time
                        <span class="pd-history-cell pd-payment-late pd-history-key ms-2"></span> Paid late (past grace period)
                        <span class="pd-history-cell pd-payment-overdue pd-history-key ms-2"></span> Overdue
                        <span class="pd-history-cell pd-payment-pending pd-history-key ms-2"></span> Not overdue yet
                        · Hover a risk badge for details · Reminders go to the guardian's mobile number.
                    </p>
                @endif
