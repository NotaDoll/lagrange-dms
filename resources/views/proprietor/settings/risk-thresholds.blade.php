<fieldset id="risk-thresholds" class="border rounded-3 p-3 mb-4">
    <legend class="float-none w-auto px-2 h6">Payment risk thresholds</legend>
    <p class="small text-muted">These four rules determine payment risk: 0 tripped = Low, 1 = Medium, 2 or more = High. A month counts as late when any charge in it is paid more than the grace period after its due date. Saving applies to the dashboard immediately.</p>
    <div class="row g-3">
        @foreach ([
            'grace_days' => ['Grace period (days late still counted as on time)', 0, 60],
            'late_payments' => ['Late months in the last 6 (at least)', 1, 6],
            'consecutive_months' => ['Consecutive late months (at least)', 1, 255],
            'average_days' => ['Average days overdue (more than)', 0, 3650],
            'recent_default_days' => ['Recent default within (days)', 1, 3650],
        ] as $key => [$label, $min, $max])
            <div class="col-md-6"><label class="form-label" for="risk_{{ $key }}">{{ $label }}</label><input class="form-control" style="min-height:44px" type="number" step="1" min="{{ $min }}" max="{{ $max }}" name="risk_{{ $key }}" id="risk_{{ $key }}" value="{{ old('risk_'.$key, $riskThresholds[$key]) }}" required>@error('risk_'.$key)<div class="small text-danger">{{ $message }}</div>@enderror</div>
        @endforeach
    </div>
</fieldset>
