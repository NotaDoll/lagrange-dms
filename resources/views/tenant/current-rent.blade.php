<div class="small text-muted mt-2">
    @if ($rentTerms)
        <div>{{ $rentTerms['location'] }} · ₱{{ number_format($rentTerms['monthly_rate'], 2) }}/month</div>
        @php($next = $rentTerms['next_due'] ?? null)
        @if ($next)
            @php($days = (int) \Illuminate\Support\Carbon::parse(\App\Models\Tenant::localDate())->diffInDays($next['date']))
            @if ($next['overdue'])
                <div class="text-danger fw-semibold">{{ $next['label'] }} due {{ $next['date']->format('M j, Y') }} · overdue · ₱{{ number_format($next['amount'], 2) }}</div>
            @else
                <div>Next {{ Str::lower($next['label']) }} due <strong class="text-dark">{{ $next['date']->format('M j, Y') }}</strong> · {{ $days === 0 ? 'today' : ($days === 1 ? 'tomorrow' : 'in '.$days.' days') }} · ₱{{ number_format($next['amount'], 2) }}</div>
            @endif
        @else
            <div>No upcoming {{ Str::lower($rentTerms['label']) }} due.</div>
        @endif
    @else
        <div>No room is currently assigned.</div>
    @endif
</div>
