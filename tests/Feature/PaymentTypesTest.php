<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingPolicy;
use App\Services\PaymentLedgerService;
use App\Services\TenantBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Rent is due every month on the tenant's move-in day, and the Record Payment form
 * picks the month automatically. This tenant moved in on Aug 12, so the first rent is due Sep 12.
 * "Today" is Oct 19: Sep 12 and Oct 12 are owed; Nov 12 is the next upcoming due date.
 */
class PaymentTypesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private int $receipts = 100;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(10, 0));
        $this->tenant = Tenant::create(['user_id' => User::factory()->create()->id, 'move_in_date' => '2026-08-12']);
        $room = Room::create(['room_number' => '101', 'floor' => '1', 'capacity' => 1, 'monthly_rate' => 3000]);
        $bed = Bed::create(['room_id' => $room->id, 'bed_label' => 'A', 'status' => 'available']);
        RoomAssignment::create(['tenant_id' => $this->tenant->id, 'bed_id' => $bed->id, 'start_date' => '2026-08-12', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['role' => 'proprietor']));
    }

    private function pay(string $type, float $amount, array $extra = [])
    {
        return $this->post(route('proprietor.payments.store'), $extra + [
            'tenant_id' => $this->tenant->id, 'amount' => $amount, 'paid_date' => '2026-10-19', 'payment_type' => $type,
            'receipt_number' => 'OR-'.(++$this->receipts),
        ]);
    }

    private function remaining(string $month): float
    {
        return app(TenantBalanceService::class)->monthBalance($this->tenant->fresh(), Carbon::parse($month))['remaining'];
    }

    public function test_rent_is_due_on_the_move_in_day_without_prorating(): void
    {
        $entries = app(TenantBalanceService::class)->billingEntries($this->tenant, '2026-08-01', '2026-10-31');
        $this->assertSame(['2026-09-12', '2026-10-12'], $entries->map(fn ($e) => $e->due_date->toDateString())->all());
        $this->assertSame(['3000.00', '3000.00'], $entries->pluck('amount')->all()); // first month is a full month too

        $policy = app(BillingPolicy::class);
        $lateMonth = new Tenant(['move_in_date' => '2026-01-31', 'status' => Tenant::STATUS_ACTIVE]);
        $this->assertSame('2026-02-28', $policy->dueDate($lateMonth, Carbon::parse('2026-02-01'))->toDateString());
        $this->assertSame('2026-02-28', $policy->firstRentDueDate($lateMonth)->toDateString());
        $this->assertSame('2026-03-31', $policy->dueDate($lateMonth, Carbon::parse('2026-03-01'))->toDateString());
    }

    public function test_move_in_advance_reduces_rent_and_carries_forward_once(): void
    {
        $this->tenant->update(['move_in_date' => '2026-08-01', 'advance_payment' => 3500, 'security_deposit' => 3000]);
        $this->tenant->roomAssignments()->update(['start_date' => '2026-08-01']);
        $this->travelTo(now()->setDate(2026, 8, 20)->setTime(10, 0));
        $this->get(route('proprietor.payments.index', ['tenant_id' => $this->tenant->id]))->assertOk()
            ->assertSee('Sep 01, 2026')->assertDontSee('Aug 01, 2026')->assertSee('Advance applied to rent');
        $this->assertSame(0.0, app(TenantBalanceService::class)->outstandingSummary($this->tenant->fresh())['outstanding_balance']);

        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(10, 0));
        $this->getJson(route('proprietor.payments.month-balance', ['tenant_id' => $this->tenant->id, 'payment_type' => 'full']))
            ->assertOk()->assertJson(['due_date' => 'Oct 01, 2026', 'amount' => 3000, 'advance_applied' => 500, 'remaining' => 2500]);
        $this->get(route('proprietor.reports.index', ['tenant_id' => $this->tenant->id, 'date_from' => '2026-10-01', 'date_to' => '2026-10-31']))
            ->assertOk()->assertViewHas('totalBilled', 3000.0)->assertViewHas('totalCollected', 500.0)->assertViewHas('totalOutstanding', 2500.0);
        $this->pay('full', 2500)->assertRedirect();
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(0.0, $this->remaining('2026-10-01'));
        $this->assertSame(3000.0, $this->remaining('2026-11-01'));
    }

    public function test_mark_paid_collects_only_the_uncovered_portion_of_a_recorded_rent_charge(): void
    {
        $this->tenant->update(['advance_payment' => 1000]);
        $charge = $this->tenant->payments()->create(['amount' => 3000, 'due_date' => '2026-09-12', 'status' => 'pending']);
        $this->get(route('proprietor.payments.index', ['status' => 'overdue']))->assertOk()->assertSee('2,000.00');

        $this->patch(route('proprietor.payments.settle', $charge), ['paid_date' => '2026-10-19', 'receipt_number' => 'OR-ADV-1'])
            ->assertRedirect();
        $this->assertDatabaseHas('payments', ['receipt_number' => 'OR-ADV-1', 'amount' => 2000, 'status' => 'paid']);
        $this->assertSame(0.0, $this->remaining('2026-09-01'));
        $this->assertSame(3000.0, $this->remaining('2026-10-01'));
    }

    public function test_imported_rent_is_aligned_to_the_move_in_day(): void
    {
        $payment = app(PaymentLedgerService::class)->create([
            'tenant_id' => $this->tenant->id, 'amount' => 3000, 'status' => 'pending', 'due_date' => '2026-09-30',
        ]);

        $this->assertSame('2026-09-12', $payment->due_date->toDateString());
        $this->assertSame(3000.0, $this->remaining('2026-09-01'));
    }

    public function test_full_payment_goes_to_the_oldest_unpaid_month_and_must_match_it(): void
    {
        $this->pay('full', 2500)->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payments', 0);

        $this->getJson(route('proprietor.payments.month-balance', ['tenant_id' => $this->tenant->id, 'payment_type' => 'full']))
            ->assertOk()->assertJson(['ok' => true, 'month' => 'September 2026', 'due_date' => 'Sep 12, 2026', 'overdue' => true, 'remaining' => 3000]);

        $this->pay('full', 3000)->assertRedirect(route('proprietor.payments.index'));
        $this->assertDatabaseHas('payments', ['due_date' => '2026-09-12 00:00:00', 'amount' => 3000, 'status' => 'paid', 'payment_type' => 'full', 'days_overdue' => 37]);

        $this->pay('full', 3000)->assertRedirect(); // next one is October
        $this->assertDatabaseHas('payments', ['due_date' => '2026-10-12 00:00:00', 'payment_type' => 'full', 'days_overdue' => 7]);

        $this->pay('full', 3000)->assertSessionHasErrors('payment_type'); // nothing due until Nov 12
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_partial_payment_leaves_the_rest_outstanding_until_paid_in_full(): void
    {
        $this->pay('partial', 3000)->assertSessionHasErrors('amount'); // not less than the balance

        $this->pay('partial', 1000)->assertRedirect();
        $this->assertSame(2000.0, $this->remaining('2026-09-01'));
        $this->getJson(route('proprietor.payments.month-balance', ['tenant_id' => $this->tenant->id, 'payment_type' => 'full']))
            ->assertOk()->assertJson(['month' => 'September 2026', 'amount' => 3000, 'paid' => 1000, 'remaining' => 2000]);

        $this->pay('full', 2000)->assertRedirect();
        $this->assertSame(0.0, $this->remaining('2026-09-01'));
        $this->assertSame(3000.0, $this->remaining('2026-10-01'));
        $this->assertSame(['partial', 'full'], Payment::orderBy('id')->pluck('payment_type')->all());
        $this->get(route('proprietor.payments.index'))->assertOk()->assertSee('Partial')->assertSee('Full');
    }

    public function test_advance_needs_a_clear_balance_then_spreads_over_upcoming_months(): void
    {
        $this->pay('advance', 3000)->assertSessionHasErrors('payment_type'); // still owes September
        $this->getJson(route('proprietor.payments.month-balance', ['tenant_id' => $this->tenant->id, 'payment_type' => 'advance']))
            ->assertOk()->assertJson(['ok' => false]);

        $this->pay('full', 3000)->assertRedirect();
        $this->pay('full', 3000)->assertRedirect();
        $this->pay('advance', 40000)->assertSessionHasErrors('amount'); // more than 12 months of rent

        $this->pay('advance', 7000)->assertRedirect();
        $rows = Payment::where('payment_type', 'advance')->orderBy('due_date')->get();
        $this->assertSame(['2026-11-12', '2026-12-12', '2027-01-12'], $rows->map(fn ($r) => $r->due_date->toDateString())->all());
        $this->assertSame(['3000.00', '3000.00', '1000.00'], $rows->pluck('amount')->all());
        $this->assertSame([0], $rows->pluck('days_overdue')->unique()->values()->all());
        $base = preg_replace('/-\d+$/', '', $rows->first()->receipt_number);
        $this->assertSame([$base.'-1', $base.'-2', $base.'-3'], $rows->pluck('receipt_number')->all());
        $this->assertSame(2000.0, $this->remaining('2027-01-01'));
        $this->assertDatabaseHas('notifications', ['type' => 'payment_confirmation']);
    }

    public function test_proprietor_can_enter_the_receipt_number(): void
    {
        $this->pay('full', 3000, ['receipt_number' => 'OR-0456'])->assertRedirect();
        $this->assertDatabaseHas('payments', ['due_date' => '2026-09-12 00:00:00', 'receipt_number' => 'OR-0456']);
        $this->pay('full', 3000, ['receipt_number' => 'OR-0456'])->assertSessionHasErrors('receipt_number');

        // Mark paid in the ledger takes a receipt number too.
        $charge = Payment::create(['tenant_id' => $this->tenant->id, 'amount' => 3000, 'due_date' => '2026-10-12 00:00:00', 'status' => 'pending']);
        $this->patch(route('proprietor.payments.settle', $charge), ['paid_date' => '2026-10-19'])->assertSessionHasErrors('receipt_number');
        $this->assertNotSame('paid', $charge->fresh()->status);
        $this->patch(route('proprietor.payments.settle', $charge), ['paid_date' => '2026-10-19', 'receipt_number' => 'OR-0456'])->assertSessionHasErrors('receipt_number');
        $this->patch(route('proprietor.payments.settle', $charge), ['paid_date' => '2026-10-19', 'receipt_number' => 'OR-0457'])->assertRedirect();
        $this->assertSame(['paid', 'OR-0457', 'full'], [$charge->fresh()->status, $charge->fresh()->receipt_number, $charge->fresh()->payment_type]);

        // The receipt number is required.
        $this->pay('advance', 3000, ['receipt_number' => ''])->assertSessionHasErrors('receipt_number');
        $this->assertDatabaseMissing('payments', ['payment_type' => 'advance']);
    }

    public function test_payment_type_is_required_and_status_and_due_date_are_not_entered(): void
    {
        $entry = ['tenant_id' => $this->tenant->id, 'amount' => 3000, 'paid_date' => '2026-10-19', 'receipt_number' => 'OR-1'];
        $this->post(route('proprietor.payments.store'), $entry)->assertSessionHasErrors('payment_type');
        $this->post(route('proprietor.payments.store'), ['payment_type' => 'full', 'paid_date' => '2026-10-20'] + $entry)->assertSessionHasErrors('paid_date');
        $this->assertDatabaseCount('payments', 0);

        // A stray status or due date is ignored: money is always Paid, against the month the system picks.
        $this->post(route('proprietor.payments.store'), $entry + ['payment_type' => 'full', 'status' => 'pending', 'due_date' => '2027-05-01'])->assertRedirect();
        $this->assertDatabaseHas('payments', ['status' => 'paid', 'payment_type' => 'full', 'due_date' => '2026-09-12 00:00:00']);
        $this->get(route('proprietor.payments.create'))->assertOk()->assertSee('Apply payment to')->assertSee('Receipt No.')->assertSee('Note')
            ->assertDontSee('name="payment_type"', false)
            ->assertDontSee('name="status"', false)->assertDontSee('Due Date');
    }

    public function test_rent_payment_clears_the_first_two_of_three_months_under_one_receipt_with_a_note(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(10, 0));
        $fields = ['tenant_id' => $this->tenant->id, 'applies_to' => 'rent', 'amount' => 6000];
        $this->getJson(route('proprietor.payments.preview', $fields))->assertOk()->assertExactJson(['allocations' => [
            ['due_date' => 'Sep 12, 2026', 'amount' => 3000, 'remaining' => 0, 'upcoming' => false],
            ['due_date' => 'Oct 12, 2026', 'amount' => 3000, 'remaining' => 0, 'upcoming' => false],
        ]]);
        $this->post(route('proprietor.payments.store'), $fields + [
            'receipt_number' => 'OR-RENT-1', 'paid_date' => '2026-11-19', 'note' => 'Paid two months at the office.',
        ])->assertRedirect(route('proprietor.payments.index'));

        $rows = $this->tenant->payments()->orderBy('due_date')->get();
        $this->assertSame(['2026-09-12', '2026-10-12'], $rows->map(fn (Payment $row) => $row->due_date->toDateString())->all());
        $this->assertSame(['OR-RENT-1', null], $rows->pluck('receipt_number')->all());
        $this->assertSame(['OR-RENT-1', 'OR-RENT-1'], $rows->pluck('display_receipt')->all());
        $this->assertSame(['Paid two months at the office.', 'Paid two months at the office.'], $rows->pluck('note')->all());
        $this->assertSame(3000.0, $this->remaining('2026-11-01'));
        $this->assertSame(1, app(TenantBalanceService::class)->summary($this->tenant->fresh())['paid_count']);
        $this->get(route('proprietor.payments.history', $this->tenant))->assertOk()->assertSee('Paid two months at the office.');
    }

    public function test_future_rent_requires_confirmation_before_saving_any_allocation(): void
    {
        $fields = [
            'tenant_id' => $this->tenant->id, 'applies_to' => 'rent', 'amount' => 6500,
            'receipt_number' => 'OR-RENT-2', 'paid_date' => '2026-10-19',
        ];
        $this->post(route('proprietor.payments.store'), $fields)->assertSessionHasErrors('apply_excess');
        $this->assertDatabaseCount('payments', 0);

        $this->post(route('proprietor.payments.store'), $fields + ['apply_excess' => '1'])->assertRedirect();
        $this->assertSame(['full', 'full', 'advance'], $this->tenant->payments()->orderBy('due_date')->pluck('payment_type')->all());
        $this->assertSame(2500.0, $this->remaining('2026-11-01'));
    }

    public function test_unpaid_status_updates_itself_from_the_due_date(): void
    {
        $charge = Payment::create(['tenant_id' => $this->tenant->id, 'amount' => 3000, 'due_date' => '2026-10-31 00:00:00', 'status' => 'pending']);
        $this->assertSame(['pending', 'Not paid', 0], [$charge->current_status, $charge->status_label, $charge->current_days_overdue]);
        $this->get(route('proprietor.payments.index', ['status' => 'pending']))->assertOk()->assertSee('Not paid')->assertSee('3,000.00');

        $this->travelTo(now()->setDate(2026, 11, 3)->setTime(10, 0)); // three days after the due date, nothing re-saved
        $charge = $charge->fresh();
        $this->assertSame(['overdue', 'Overdue', 3], [$charge->current_status, $charge->status_label, $charge->current_days_overdue]);
        $this->get(route('proprietor.payments.index', ['status' => 'overdue']))->assertOk()->assertSee('3,000.00');
        $this->get(route('proprietor.payments.index', ['status' => 'pending']))->assertOk()->assertDontSee('3,000.00');
    }

    public function test_payments_apply_to_a_recorded_unpaid_charge_instead_of_double_billing(): void
    {
        // e.g. an imported "pending" rent row for September with an end-of-month due date
        $charge = Payment::create(['tenant_id' => $this->tenant->id, 'amount' => 3000, 'due_date' => '2026-09-30 00:00:00', 'status' => 'pending']);
        $this->get(route('proprietor.payments.index'))->assertOk()->assertSee('Mark paid');

        // Partial splits the charge: 1000 paid, 2000 still unpaid.
        $this->pay('partial', 1000)->assertRedirect();
        $this->assertSame('2000.00', $charge->fresh()->amount);
        $this->assertNotSame('paid', $charge->fresh()->status);
        $this->assertDatabaseHas('payments', ['amount' => 1000, 'status' => 'paid', 'payment_type' => 'partial', 'due_date' => '2026-09-30 00:00:00']);
        $this->assertSame(2000.0, $this->remaining('2026-09-01'));

        // Full settles what is left on the original row; no extra September rent row is created.
        $this->pay('full', 2000)->assertRedirect();
        $this->assertDatabaseCount('payments', 2);
        $this->assertSame(['paid', 'full'], [$charge->fresh()->status, $charge->fresh()->payment_type]);
        // Only October's rent is still owed.
        $this->assertSame(3000.0, app(TenantBalanceService::class)->outstandingSummary($this->tenant->fresh())['outstanding_balance']);
    }
}
