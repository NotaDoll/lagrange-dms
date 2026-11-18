<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UtilityBill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DueDateAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_imported_rows_move_to_the_move_in_day_and_dry_run_changes_nothing(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(10, 0));
        $tenant = Tenant::create(['user_id' => User::factory()->create()->id, 'move_in_date' => '2025-10-14', 'status' => 'active']);
        $late31 = Tenant::create(['user_id' => User::factory()->create()->id, 'move_in_date' => '2026-01-31', 'status' => 'active']);
        $reserved = Tenant::create(['user_id' => User::factory()->create()->id, 'move_in_date' => '2026-12-05', 'status' => Tenant::STATUS_RESERVED]);

        $paid = Payment::create(['tenant_id' => $tenant->id, 'amount' => 3000, 'due_date' => '2026-10-30', 'paid_date' => '2026-10-20', 'status' => 'paid', 'days_overdue' => 0]);
        $unpaid = Payment::create(['tenant_id' => $tenant->id, 'amount' => 3000, 'due_date' => '2026-11-30', 'status' => 'pending', 'days_overdue' => 0]);
        $february = Payment::create(['tenant_id' => $late31->id, 'amount' => 2000, 'due_date' => '2026-02-28', 'paid_date' => '2026-03-02', 'status' => 'paid', 'days_overdue' => 2]);
        $opening = Payment::create(['tenant_id' => $tenant->id, 'amount' => 500, 'due_date' => '2026-01-01', 'status' => 'pending', 'charge_type' => Payment::OPENING_BALANCE]);
        $room = Room::create(['room_number' => '101', 'floor' => '1', 'capacity' => 1, 'monthly_rate' => 3000]);
        $bill = UtilityBill::create(['room_id' => $room->id, 'bill_type' => 'water', 'billing_period_start' => '2026-10-01', 'billing_period_end' => '2026-10-31', 'amount' => 100, 'due_date' => '2026-11-30', 'status' => 'pending']);
        $utility = Payment::create(['tenant_id' => $tenant->id, 'amount' => 100, 'due_date' => '2026-11-30', 'status' => 'pending', 'utility_bill_id' => $bill->id]);
        $reservedRow = Payment::create(['tenant_id' => $reserved->id, 'amount' => 1000, 'due_date' => '2026-12-30', 'status' => 'pending']);

        $this->artisan('payments:align-due-dates')->assertSuccessful();
        $this->assertSame('2026-10-30', $paid->fresh()->due_date->toDateString()); // dry run: unchanged

        $this->artisan('payments:align-due-dates --apply')->assertSuccessful();
        // Paid Oct 20 against an Oct 14 due date: now 6 days late.
        $this->assertSame(['2026-10-14', 6, 'paid'], [$paid->fresh()->due_date->toDateString(), $paid->fresh()->days_overdue, $paid->fresh()->status]);
        // Nov 14 has passed, so the unpaid row is now overdue by 5 days.
        $this->assertSame(['2026-11-14', 'overdue', 5], [$unpaid->fresh()->due_date->toDateString(), $unpaid->fresh()->status, $unpaid->fresh()->days_overdue]);
        // Moved in on the 31st: February stays on its last day.
        $this->assertSame('2026-02-28', $february->fresh()->due_date->toDateString());
        // Opening balances, utility shares and not-yet-moved-in tenants keep their dates.
        $this->assertSame('2026-01-01', $opening->fresh()->due_date->toDateString());
        $this->assertSame('2026-11-30', $utility->fresh()->due_date->toDateString());
        $this->assertSame('2026-12-30', $reservedRow->fresh()->due_date->toDateString());

        $this->artisan('payments:align-due-dates')->expectsOutputToContain('already due')->assertSuccessful();
    }
}
