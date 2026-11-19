<?php

namespace Tests\Feature;

use App\Services\PaymentRiskService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** The four payment risk rules on hand-made charge lists, as of Oct 31, 2026, with the default thresholds. */
class PaymentRiskRulesTest extends TestCase
{
    private function day(string $date): int
    {
        $carbon = Carbon::parse($date);

        return intdiv($carbon->getTimestamp() + $carbon->getOffset(), 86400);
    }

    /** @param list<array{0: string, 1: ?string}> $charges [due date, paid date or null if unpaid] */
    private function evaluate(array $charges, string $asOf = '2026-10-31'): array
    {
        $prepared = array_map(fn ($c) => [
            'due' => $this->day($c[0]),
            'month' => Carbon::parse($c[0])->year * 12 + Carbon::parse($c[0])->month - 1,
            'paid' => $c[1] === null ? null : $this->day($c[1]),
            'recorded' => $c[1] !== null,
            'stored' => 0,
        ], $charges);
        $risk = app(PaymentRiskService::class);

        return $risk->evaluatePrepared($prepared, Carbon::parse($asOf), PaymentRiskService::DEFAULT_THRESHOLDS);
    }

    public function test_rent_and_utility_late_in_the_same_month_count_as_one_late_month(): void
    {
        $result = $this->evaluate([
            ['2026-09-01', '2026-09-01'],
            ['2026-10-01', '2026-10-15'], // rent, 14 days late
            ['2026-10-01', '2026-10-15'], // utility share, same month
        ]);

        $this->assertSame(1, $result['late_payments_6mo']);
        $this->assertFalse($result['rules']['Repeated late payments']);
    }

    public function test_paying_within_the_grace_period_is_on_time(): void
    {
        $result = $this->evaluate([['2026-08-01', '2026-08-04'], ['2026-09-01', '2026-09-05'], ['2026-10-01', '2026-10-03']]);

        $this->assertSame([0, 0, null, 'low'], [$result['late_payments_6mo'], $result['consecutive_late_months'], $result['days_since_last_default'], $result['risk_level']]);
    }

    public function test_a_long_unpaid_charge_is_a_recent_default(): void
    {
        $result = $this->evaluate([['2026-09-01', null]]); // 60 days unpaid

        $this->assertSame(0, $result['days_since_last_default']);
        $this->assertTrue($result['rules']['Recent default']);
    }

    public function test_recent_default_counts_from_when_the_late_charge_was_paid(): void
    {
        $result = $this->evaluate([['2026-08-01', '2026-10-21'], ['2026-09-01', '2026-09-01'], ['2026-10-01', '2026-10-01']]);

        $this->assertSame(10, $result['days_since_last_default']);
    }

    public function test_old_lateness_falls_out_of_the_six_month_window(): void
    {
        // Late Jan–Mar 2026 (more than 6 months before Oct 31), on time since and settled long ago.
        $charges = [['2026-01-01', '2026-01-20'], ['2026-02-01', '2026-02-20'], ['2026-03-01', '2026-03-20']];
        foreach (['04', '05', '06', '07', '08', '09', '10'] as $month) {
            $charges[] = ["2026-$month-01", "2026-$month-01"];
        }
        $result = $this->evaluate($charges);

        $this->assertSame([0, 0, 0.0, 'low'], [$result['late_payments_6mo'], $result['consecutive_late_months'], $result['avg_days_overdue'], $result['risk_level']]);
    }

    public function test_streak_is_the_current_one_and_skips_a_month_still_in_grace(): void
    {
        $late = fn (string $month) => ["2026-$month-01", "2026-$month-20"];
        // Late Jun–Jul, on time Aug, late Sep; Oct 28 rent is 3 days unpaid (still in grace).
        $result = $this->evaluate([$late('06'), $late('07'), ['2026-08-01', '2026-08-01'], $late('09'), ['2026-10-28', null]]);

        $this->assertSame(1, $result['consecutive_late_months']);
        $this->assertSame(3, $result['late_payments_6mo']);

        // Without the on-time August, the streak runs Jun through Sep.
        $result = $this->evaluate([$late('06'), $late('07'), $late('08'), $late('09'), ['2026-10-28', null]]);
        $this->assertSame(4, $result['consecutive_late_months']);
    }
}
