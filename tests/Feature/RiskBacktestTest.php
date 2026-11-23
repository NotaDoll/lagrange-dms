<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RiskBacktest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Four tenants, all moved in May 10 (rent of 1,000 due on the 10th from June). "Today" is Dec 20,
 * so with a 2-month window the last two cutoffs are Aug 31 (outcome Sep–Oct) and Sep 30 (Oct–Nov).
 */
class RiskBacktestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 12, 20)->setTime(10, 0));
    }

    private function tenant(string $name): Tenant
    {
        $tenant = Tenant::create(['user_id' => User::factory()->create(['name' => $name, 'role' => 'tenant'])->id, 'status' => 'active', 'move_in_date' => '2026-05-10']);
        $room = Room::create(['room_number' => $name, 'floor' => '1', 'capacity' => 1, 'monthly_rate' => 1000]);
        $bed = Bed::create(['room_id' => $room->id, 'bed_label' => 'A', 'status' => 'available']);
        RoomAssignment::create(['tenant_id' => $tenant->id, 'bed_id' => $bed->id, 'start_date' => '2026-05-10', 'status' => 'active']);

        return $tenant;
    }

    /** @param array<string, string> $paid due month (MM) => paid date */
    private function pays(Tenant $tenant, array $paid): void
    {
        foreach ($paid as $month => $date) {
            Payment::create(['tenant_id' => $tenant->id, 'amount' => 1000, 'due_date' => "2026-$month-10", 'paid_date' => $date, 'status' => 'paid', 'payment_type' => 'full']);
        }
    }

    private function people(): void
    {
        $this->tenant('Chronic'); // never pays: flagged and defaults → true positive
        $this->pays($this->tenant('Reliable'), ['06' => '2026-06-10', '07' => '2026-07-10', '08' => '2026-08-10', '09' => '2026-09-10', '10' => '2026-10-10', '11' => '2026-11-10', '12' => '2026-12-10']);
        // 20 days late in Jun–Aug, then on time: flagged, but pays on time afterwards → false positive
        $this->pays($this->tenant('Turned Around'), ['06' => '2026-06-30', '07' => '2026-07-30', '08' => '2026-08-30', '09' => '2026-09-10', '10' => '2026-10-10', '11' => '2026-11-10', '12' => '2026-12-10']);
        // On time until August, then stops paying: not flagged High in time → false negative
        $this->pays($this->tenant('Slipped'), ['06' => '2026-06-10', '07' => '2026-07-10', '08' => '2026-08-10']);
    }

    public function test_backtest_scores_flags_against_what_tenants_did_next(): void
    {
        $this->people();
        $result = app(RiskBacktest::class)->run(months: 2, windowMonths: 2, graceDays: 5);

        $this->assertSame(['2026-08-31', '2026-09-30'], array_column($result['cutoffs'], 'cutoff'));
        $outcome = fn (string $cutoff, string $name) => collect($result['observations'])->first(fn ($o) => $o['cutoff'] === $cutoff && $o['tenant'] === $name);
        $this->assertSame([true, true], [$outcome('2026-08-31', 'Chronic')['flagged'], $outcome('2026-08-31', 'Chronic')['defaulted']]);
        $this->assertSame([false, false], [$outcome('2026-08-31', 'Reliable')['flagged'], $outcome('2026-08-31', 'Reliable')['defaulted']]);
        // Turned Around has late-payment history but no charge currently overdue, so they are not High.
        $this->assertSame([false, false], [$outcome('2026-08-31', 'Turned Around')['flagged'], $outcome('2026-08-31', 'Turned Around')['defaulted']]);
        $this->assertSame([false, true], [$outcome('2026-08-31', 'Slipped')['flagged'], $outcome('2026-08-31', 'Slipped')['defaulted']]);
        $this->assertSame('medium', $outcome('2026-09-30', 'Slipped')['risk_level']); // one rule: the Sep 10 rent is 20 days unpaid

        $this->assertSame(['tenants_checked' => 8, 'tp' => 2, 'fp' => 0, 'fn' => 2, 'tn' => 4, 'accuracy' => 75.0, 'false_positive_rate' => 0.0,
            'flagged_but_paid_on_time' => 0.0, 'precision' => 100.0, 'recall' => 50.0], $result['totals']);

        // Counting Medium as flagged catches "Slipped" at the Sep 30 cutoff.
        $this->assertSame(3, app(RiskBacktest::class)->run(months: 2, windowMonths: 2, graceDays: 5, flagAt: 'medium')['totals']['tp']);
    }

    public function test_command_prints_the_rates_and_writes_a_csv(): void
    {
        $this->people();
        $csv = tempnam(sys_get_temp_dir(), 'backtest').'.csv';

        $this->artisan('risk:backtest', ['--months' => 2, '--csv' => $csv])
            ->expectsOutputToContain('accuracy 75% (target 80% or more)')
            ->expectsOutputToContain('Flagged but paid on time 0%')
            ->assertSuccessful();

        $rows = array_map('str_getcsv', file($csv, FILE_IGNORE_NEW_LINES));
        $this->assertSame(['cutoff', 'tenant_id', 'tenant', 'risk_level', 'flagged', 'worst_days_late', 'defaulted'], $rows[0]);
        $this->assertCount(9, $rows); // header + 4 tenants x 2 cutoffs
        @unlink($csv);
    }

    public function test_command_explains_when_there_is_no_history_yet(): void
    {
        $this->artisan('risk:backtest')->expectsOutputToContain('No tenant had charges due')->assertSuccessful();
    }
}
