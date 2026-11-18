<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Complaint;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\SmsLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantDetailsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(10, 0));
        $this->actingAs(User::factory()->create(['role' => 'proprietor']));

        // Moved in Aug 14: rent of 3,000 due Sep 14, Oct 14 and Nov 14.
        $this->tenant = Tenant::create([
            'user_id' => User::factory()->create(['name' => 'Juan Dela Cruz', 'role' => 'tenant'])->id,
            'status' => 'active', 'move_in_date' => '2026-08-14',
            'guardian_name' => 'Maria Dela Cruz', 'guardian_contact_number' => '09171234567',
        ]);
        $room = Room::create(['room_number' => '201', 'floor' => '2', 'capacity' => 1, 'monthly_rate' => 3000]);
        $bed = Bed::create(['room_id' => $room->id, 'bed_label' => 'A', 'status' => 'available']);
        RoomAssignment::create(['tenant_id' => $this->tenant->id, 'bed_id' => $bed->id, 'start_date' => '2026-08-14', 'status' => 'active']);
        Payment::create(['tenant_id' => $this->tenant->id, 'amount' => 3000, 'due_date' => '2026-09-14', 'paid_date' => '2026-09-20',
            'status' => 'paid', 'payment_type' => 'full', 'receipt_number' => 'OR-0456', 'days_overdue' => 6]);
    }

    public function test_list_rows_open_the_tenant_page_without_action_buttons(): void
    {
        $show = route('proprietor.tenants.show', $this->tenant);

        $this->get(route('proprietor.tenants.index'))->assertOk()
            ->assertSee('data-href="'.$show.'"', false)
            ->assertSee('href="'.$show.'"', false)
            ->assertDontSee('>Actions<', false)
            ->assertDontSee('tenantModal', false)
            ->assertDontSee('>Delete<', false);
    }

    public function test_tenant_page_shows_details_balance_history_and_activity(): void
    {
        Complaint::create(['tenant_id' => $this->tenant->id, 'category' => 'maintenance', 'description' => 'Leaking faucet', 'status' => Complaint::STATUS_IN_PROGRESS]);
        SmsLog::create(['tenant_id' => $this->tenant->id, 'recipient_number' => '09171234567', 'recipient_type' => 'guardian',
            'message' => 'Dear Maria Dela Cruz, ...', 'status' => 'failed', 'error_message' => 'TextBee rejected access. Check the API key and account permissions.']);

        $this->get(route('proprietor.tenants.show', $this->tenant))->assertOk()
            // Breadcrumbs and header
            ->assertSee('aria-label="Breadcrumb"', false)->assertSeeInOrder(['Tenants', 'Juan Dela Cruz'])
            ->assertSee('Room 201')
            // Summary: Oct 14 and Nov 14 unpaid
            ->assertViewHas('summary', fn ($s) => $s['outstanding_balance'] === 6000.0 && $s['unpaid_months'] === 2)
            ->assertSee('₱6,000.00')->assertSee('Due every 14th of the month')
            // Payment history, newest first, with the receipt and lateness
            ->assertViewHas('history', fn ($rows) => $rows->map(fn ($r) => $r->due_date->toDateString())->all() === ['2026-11-14', '2026-10-14', '2026-09-14'])
            ->assertSee('OR-0456')->assertSee('6 days late')->assertSee('36 days late')
            // Activity
            ->assertSee('Leaking faucet')
            ->assertSee('TextBee rejected access')
            ->assertSee('Room history')
            // Kebab menu
            ->assertSee('More actions for Juan Dela Cruz')
            ->assertSee('Edit details')->assertSee('Open in Payment Ledger')->assertSee('Delete tenant');
    }

    public function test_edit_page_has_breadcrumbs_back_to_the_tenant_and_saving_returns_there(): void
    {
        $show = route('proprietor.tenants.show', $this->tenant);

        $this->get(route('proprietor.tenants.edit', $this->tenant))->assertOk()
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('href="'.$show.'"', false)
            ->assertSeeInOrder(['Tenants', 'Juan Dela Cruz', 'Edit']);
    }

    public function test_tenant_import_pages_are_not_mistaken_for_a_tenant(): void
    {
        $this->get(route('proprietor.tenants.import.create'))->assertOk()->assertSee('Bulk import');
        $this->get('/proprietor/tenants/999999')->assertNotFound();
    }

    public function test_tenants_cannot_open_another_tenants_page(): void
    {
        $this->actingAs($this->tenant->user)->get(route('proprietor.tenants.show', $this->tenant))->assertForbidden();
    }
}
