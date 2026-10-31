<div class="small text-muted mt-2">
    @if ($rentTerms)
        <div>{{ $rentTerms['room'] }} / {{ $rentTerms['bed'] }} · ₱{{ number_format($rentTerms['monthly_rate'], 2) }}/month</div>
        <div>Effective {{ $rentTerms['effective_from']->format('M j, Y') }} · Rent due {{ $rentTerms['due_date']->format('M j, Y') }}</div>
    @else
        <div>No room is currently assigned.</div>
    @endif
</div>
