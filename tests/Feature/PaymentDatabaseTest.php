<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_and_settling_a_charge_updates_ledger_and_report_without_duplication(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(10, 0));
        $tenant = Tenant::create(['user_id' => User::factory()->create()->id, 'move_in_date' => '2026-10-01']);
        $this->actingAs(User::factory()->create(['role' => 'proprietor']));
        // A charge recorded as "pending" earlier (e.g. by the Excel import) turns Overdue on its own once past due.
        $payment = Payment::create(['tenant_id' => $tenant->id, 'amount' => 2500, 'due_date' => '2026-10-01', 'status' => 'pending']);
        $this->assertSame(['overdue', 'Overdue', 18], [$payment->current_status, $payment->status_label, $payment->current_days_overdue]);
        $this->get(route('proprietor.payments.index', ['status' => 'overdue']))->assertOk()->assertSee('2,500.00')->assertSee('Overdue');
        $this->get(route('proprietor.payments.index', ['status' => 'pending']))->assertOk()->assertDontSee('2,500.00');
        $this->get(route('proprietor.payments.index'))->assertOk()->assertSee('2,500.00')->assertSee('Mark paid');
        $this->get(route('proprietor.reports.index'))->assertOk()->assertViewHas('totalOutstanding', 2500.0)->assertViewHas('totalCollected', 0.0);
        $this->patch(route('proprietor.payments.settle', $payment), ['paid_date' => '2026-10-19', 'receipt_number' => 'OR-502'])->assertRedirect();
        $this->patch(route('proprietor.payments.settle', $payment), ['paid_date' => '2026-10-18', 'receipt_number' => 'OR-503'])->assertRedirect();
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid', 'days_overdue' => 18]);
        $this->assertSame('2026-10-19', $payment->fresh()->paid_date->toDateString());
        $this->assertDatabaseCount('notifications', 1); // One confirmation.
        $this->get(route('proprietor.reports.index'))->assertOk()->assertViewHas('totalBilled', 2500.0)
            ->assertViewHas('totalCollected', 2500.0)->assertViewHas('totalOutstanding', 0.0)->assertViewHas('paidLate', 1);
    }

    public function test_paid_entries_require_real_payment_date_and_unpaid_entries_cannot_have_one(): void
    {
        $tenant = Tenant::create(['user_id' => User::factory()->create()->id, 'move_in_date' => today()->toDateString()]);
        $this->actingAs(User::factory()->create(['role' => 'proprietor']));
        $entry = ['tenant_id' => $tenant->id, 'amount' => 2000, 'due_date' => today()->toDateString(), 'payment_type' => 'full'];
        $this->post(route('proprietor.payments.store'), $entry)->assertSessionHasErrors('paid_date');
        $tomorrow = \Illuminate\Support\Carbon::parse(Tenant::localDate())->addDay()->toDateString();
        $this->post(route('proprietor.payments.store'), $entry + ['paid_date' => $tomorrow])->assertSessionHasErrors('paid_date');
        unset($entry['payment_type']);
        $this->post(route('proprietor.payments.store'), $entry + ['paid_date' => Tenant::localDate()])->assertSessionHasErrors('payment_type');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_empty_ledger_and_record_form_render_with_database_backed_navigation(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'proprietor']));
        $this->get(route('proprietor.payments.index'))->assertOk()->assertSee('No payments found.')
            ->assertDontSee('class="btn btn-outline-primary mb-3">Financial Report</a>', false);
        $this->get(route('proprietor.payments.create'))->assertOk()->assertSee('Record Payment');
    }

    public function test_tenant_cannot_record_or_settle_payments(): void
    {
        $user = User::factory()->create(['role' => 'tenant']);
        $tenant = Tenant::create(['user_id' => $user->id]);
        $payment = Payment::create(['tenant_id' => $tenant->id, 'amount' => 1000, 'due_date' => today(), 'status' => 'pending']);
        $this->actingAs($user)->post(route('proprietor.payments.store'), [])->assertForbidden();
        $this->patch(route('proprietor.payments.settle', $payment), ['paid_date' => today()->toDateString()])->assertForbidden();
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    }
}
