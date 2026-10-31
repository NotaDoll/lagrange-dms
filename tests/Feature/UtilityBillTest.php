<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UtilityBill;
use App\Services\TenantBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UtilityBillTest extends TestCase
{
    use RefreshDatabase;

    private function room(string $number = '101'): Room
    {
        return Room::create(['room_number' => $number, 'floor' => '1', 'capacity' => 4, 'monthly_rate' => 1300]);
    }

    private function occupant(Room $room, string $label = 'A', string $start = '2026-09-01', ?string $end = null): Tenant
    {
        $tenant = Tenant::create(['user_id' => User::factory()->create(['role' => 'tenant'])->id, 'move_in_date' => $start]);
        $bed = Bed::create(['room_id' => $room->id, 'bed_label' => $label, 'status' => 'available']);
        RoomAssignment::create(['tenant_id' => $tenant->id, 'bed_id' => $bed->id, 'start_date' => $start, 'end_date' => $end, 'status' => $end ? 'ended' : 'active']);

        return $tenant;
    }

    private function billData(Room $room, array $overrides = []): array
    {
        return array_replace([
            'room_id' => $room->id, 'bill_type' => 'electricity', 'billing_period_start' => '2026-10-01',
            'billing_period_end' => '2026-10-31', 'amount' => '100.00', 'due_date' => '2026-10-31', 'status' => 'pending',
        ], $overrides);
    }

    private function admin(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 19));
        $this->actingAs(User::factory()->create(['role' => 'proprietor']));
    }

    public function test_room_bill_splits_exact_cents_and_updates_balances_without_reducing_rent(): void
    {
        $this->admin();
        $room = $this->room();
        $first = $this->occupant($room);
        $this->occupant($room, 'B');
        $this->occupant($room, 'C');
        $other = $this->occupant($this->room('102'));
        $before = app(TenantBalanceService::class)->summary($first)['outstanding_balance'];
        $this->post(route('proprietor.utility-bills.store'), $this->billData($room, ['status' => 'paid', 'paid_date' => '2026-10-19']))
            ->assertRedirect(route('proprietor.utility-bills.index'));
        $bill = UtilityBill::sole();
        $this->assertSame(['33.34', '33.33', '33.33'], $bill->payments()->orderBy('tenant_id')->pluck('amount')->all());
        $this->assertSame(100.0, (float) $bill->payments()->sum('amount'));
        $this->assertDatabaseCount('payments', 3);
        $this->assertSame(0, $other->payments()->count());
        $this->assertSame($before + 33.34, app(TenantBalanceService::class)->summary($first->fresh())['outstanding_balance']);
        $this->assertSame(0, $bill->payments()->where('status', 'paid')->count());
        $this->get(route('proprietor.utility-bills.index'))->assertOk()->assertSee('tenant shares')->assertSee('Electricity');
        $this->get(route('proprietor.utility-bills.create'))->assertOk()->assertSee('Tenant share preview');
        $this->get(route('proprietor.utility-bills.edit', $bill))->assertOk();
        $this->get(route('proprietor.reports.index'))->assertOk()->assertViewHas('totalBilled', 5300.0);
        $share = $first->payments()->sole();
        $this->patch(route('proprietor.payments.settle', $share), ['paid_date' => '2026-10-19'])->assertRedirect();
        $this->assertSame($before, app(TenantBalanceService::class)->summary($first->fresh())['outstanding_balance']);
        $this->actingAs($first->user)->get(route('tenant.payments.index'))->assertOk()->assertSee('Electricity')->assertViewHas('outstandingBalance', $before);
    }

    public function test_utility_payment_does_not_pay_down_rent_for_same_month(): void
    {
        $this->admin();
        $room = $this->room();
        $tenant = $this->occupant($room);
        $this->post(route('proprietor.utility-bills.store'), $this->billData($room, [
            'billing_period_start' => '2026-09-01', 'billing_period_end' => '2026-09-30', 'due_date' => '2026-09-30',
        ]))->assertRedirect();
        $share = Payment::sole();
        $this->patch(route('proprietor.payments.settle', $share), ['paid_date' => '2026-10-19'])->assertRedirect();
        $this->assertSame(1300.0, app(TenantBalanceService::class)->summary($tenant->fresh())['outstanding_balance']);
        $this->get(route('proprietor.reports.index'))->assertOk()->assertViewHas('totalBilled', 1400.0)
            ->assertViewHas('totalCollected', 100.0)->assertViewHas('totalOutstanding', 1300.0);
    }

    public function test_bill_edits_replace_unpaid_shares_and_protect_collected_shares(): void
    {
        $this->admin();
        $room = $this->room();
        $this->occupant($room);
        $this->occupant($room, 'B');
        $this->post(route('proprietor.utility-bills.store'), $this->billData($room))->assertRedirect();
        $bill = UtilityBill::sole();
        $this->put(route('proprietor.utility-bills.update', $bill), $this->billData($room, ['amount' => '200.00']))->assertRedirect();
        $this->assertDatabaseCount('payments', 2);
        $this->assertSame(200.0, (float) $bill->payments()->sum('amount'));
        $share = $bill->payments()->first();
        $this->patch(route('proprietor.payments.settle', $share), ['paid_date' => '2026-10-19'])->assertRedirect();
        $this->put(route('proprietor.utility-bills.update', $bill), $this->billData($room, ['amount' => '300.00']))->assertSessionHasErrors('amount');
        $this->assertSame('200.00', $bill->fresh()->amount);
        $this->put(route('proprietor.utility-bills.update', $bill), $this->billData($room, ['amount' => '200.00', 'status' => 'paid', 'paid_date' => '2026-10-19', 'notes' => 'Provider paid']))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('paid', $share->fresh()->status);
        $this->assertSame(1, $bill->payments()->where('status', 'paid')->count());
        $this->delete(route('proprietor.utility-bills.destroy', $bill))->assertSessionHasErrors('bill');
        $this->assertDatabaseCount('utility_bills', 1);
    }

    public function test_period_occupants_are_snapshotted_and_other_bill_label_is_required(): void
    {
        $this->admin();
        $room = $this->room();
        $past = $this->occupant($room, 'A', '2026-09-01', '2026-09-30');
        $current = $this->occupant($room, 'B', '2026-10-01');
        $data = $this->billData($room, ['bill_type' => 'other', 'billing_period_start' => '2026-09-01', 'billing_period_end' => '2026-09-30']);
        $this->post(route('proprietor.utility-bills.store'), $data)->assertSessionHasErrors('other_description');
        $data['other_description'] = 'Garbage collection';
        $this->post(route('proprietor.utility-bills.store'), $data)->assertRedirect();
        $bill = UtilityBill::sole();
        $this->assertSame($past->id, $bill->payments()->sole()->tenant_id);
        $this->assertSame(0, $current->payments()->count());
        $this->assertSame('Garbage collection', $bill->label);
        $this->occupant($room, 'C', '2026-09-15');
        $data['notes'] = 'Notes only';
        $this->put(route('proprietor.utility-bills.update', $bill), $data)->assertRedirect();
        $this->assertSame(1, $bill->payments()->count());
        $this->delete(route('proprietor.utility-bills.destroy', $bill))->assertRedirect();
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('utility_bills', 0);
    }

    public function test_empty_rooms_invalid_dates_and_tenant_access_are_rejected(): void
    {
        $this->admin();
        $room = $this->room();
        $this->post(route('proprietor.utility-bills.store'), $this->billData($room))->assertSessionHasErrors('room_id');
        $this->assertDatabaseCount('utility_bills', 0);
        $this->post(route('proprietor.utility-bills.store'), $this->billData($room, ['billing_period_end' => '2026-09-01']))->assertSessionHasErrors('billing_period_end');
        $tenant = $this->occupant($room);
        $this->post(route('proprietor.utility-bills.store'), $this->billData($room))->assertRedirect();
        $bill = UtilityBill::sole();
        $this->actingAs($tenant->user);
        $this->get(route('proprietor.utility-bills.index'))->assertForbidden();
        $this->post(route('proprietor.utility-bills.store'), $this->billData($room))->assertForbidden();
        $this->put(route('proprietor.utility-bills.update', $bill), $this->billData($room))->assertForbidden();
        $this->delete(route('proprietor.utility-bills.destroy', $bill))->assertForbidden();
    }
}
