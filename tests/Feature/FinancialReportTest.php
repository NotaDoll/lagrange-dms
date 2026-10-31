<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_reconciles_database_totals_and_filters_every_section(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 19));
        $admin = User::factory()->create(['role' => 'proprietor']);
        $tenant = Tenant::create(['user_id' => User::factory()->create(['name' => 'Ana Report'])->id]);
        $other = Tenant::create(['user_id' => User::factory()->create()->id]);
        foreach ([
            ['amount' => 2000, 'due_date' => '2026-10-01', 'paid_date' => '2026-10-01', 'status' => 'paid'],
            ['amount' => 1500, 'due_date' => '2026-10-02', 'paid_date' => '2026-10-03', 'status' => 'paid'],
            ['amount' => 500, 'due_date' => '2026-10-03', 'status' => 'paid'],
            ['amount' => 3000, 'due_date' => '2026-10-04', 'status' => 'pending'],
            ['amount' => 4000, 'due_date' => '2026-10-25', 'status' => 'pending'],
            ['amount' => 9000, 'due_date' => '2026-09-01', 'status' => 'overdue'],
        ] as $entry) {
            Payment::create(['tenant_id' => $tenant->id] + $entry);
        }
        Payment::create(['tenant_id' => $other->id, 'amount' => 8000, 'due_date' => '2026-10-01', 'status' => 'overdue']);

        $this->actingAs($admin)->get(route('proprietor.reports.index', [
            'date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'tenant_id' => $tenant->id,
        ]))->assertOk()->assertSee('Financial Report')->assertSee('Ana Report')
            ->assertViewHas('totalBilled', 11000.0)->assertViewHas('totalCollected', 4000.0)
            ->assertViewHas('totalOutstanding', 7000.0)->assertViewHas('totalOverdue', 3000.0)
            ->assertViewHas('paidOnTime', 1)->assertViewHas('paidLate', 1)->assertViewHas('unknownPaidDate', 1)
            ->assertViewHas('tenantRows', fn ($rows) => $rows->count() === 1 && $rows->first()->balance === 7000.0)
            ->assertViewHas('months', fn ($months) => $months->count() === 1 && $months->first()->amount === 4000.0);
    }

    public function test_default_report_is_empty_and_uses_last_six_months(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'proprietor']))
            ->get(route('proprietor.reports.index'))->assertOk()
            ->assertSee('No rent charges or payments match these filters.')
            ->assertViewHas('dateFrom', now()->subMonthsNoOverflow(5)->startOfMonth()->toDateString())
            ->assertViewHas('dateTo', now()->endOfMonth()->toDateString());
    }

    public function test_filters_are_validated_and_old_collectibles_link_redirects(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'proprietor']));
        $this->get(route('proprietor.reports.index', ['date_from' => 'bad']))->assertSessionHasErrors('date_from');
        $this->get(route('proprietor.reports.index', ['date_from' => '2026-10-31', 'date_to' => '2026-10-01']))->assertSessionHasErrors('date_to');
        $this->get(route('proprietor.reports.index', ['tenant_id' => 9999]))->assertSessionHasErrors('tenant_id');
        $this->get(route('proprietor.reports.collectibles', ['date_from' => '2026-10-01']))
            ->assertRedirect(route('proprietor.reports.index', ['date_from' => '2026-10-01']));
    }

    public function test_tenants_and_guests_cannot_access_reports(): void
    {
        $this->get(route('proprietor.reports.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'tenant']))
            ->get(route('proprietor.reports.index'))->assertForbidden();
    }

    public function test_monthly_chart_includes_zero_months_and_year_boundaries(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'proprietor']))
            ->get(route('proprietor.reports.index', ['date_from' => '2025-12-01', 'date_to' => '2026-02-28']))
            ->assertOk()->assertViewHas('months', fn ($months) => $months->pluck('label')->all() === ['Dec 2025', 'Jan 2026', 'Feb 2026']);
    }
}
