<?php

namespace Tests\Feature;

use App\Models\AiSummary;
use App\Models\Bed;
use App\Models\Complaint;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProprietorDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 31)->setTime(12, 0));
        Http::preventStrayRequests();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'proprietor']);
        $this->actingAs($user);

        return $user;
    }

    private function tenant(string $name = 'Follow Up'): Tenant
    {
        return Tenant::create(['user_id' => User::factory()->create(['name' => $name, 'role' => 'tenant', 'contact_number' => '09171234567'])->id, 'status' => 'active', 'move_in_date' => '2026-08-01', 'guardian_contact_number' => '09998887777']);
    }

    private function late(Tenant $tenant, int $days = 10): Payment
    {
        Payment::create(['tenant_id' => $tenant->id, 'amount' => 1000, 'due_date' => '2026-09-01', 'days_overdue' => $days, 'status' => 'overdue']);

        return Payment::create(['tenant_id' => $tenant->id, 'amount' => 1000, 'due_date' => '2026-10-01', 'days_overdue' => $days, 'status' => 'overdue']);
    }

    public function test_dashboard_matches_rooms_financial_reports_and_open_complaints_without_side_effects(): void
    {
        $this->admin();
        $tenant = $this->tenant();
        $room = Room::create(['room_number' => '101', 'floor' => '1', 'capacity' => 2, 'monthly_rate' => 1300]);
        $bed = Bed::create(['room_id' => $room->id, 'bed_label' => 'A', 'status' => 'available']);
        Bed::create(['room_id' => $room->id, 'bed_label' => 'B', 'status' => 'available']);
        RoomAssignment::create(['tenant_id' => $tenant->id, 'bed_id' => $bed->id, 'start_date' => '2026-08-01', 'status' => 'active']);
        $this->late($tenant, 12);
        Payment::create(['tenant_id' => $tenant->id, 'amount' => 300, 'due_date' => '2026-10-01', 'paid_date' => '2026-10-01', 'status' => 'paid']);
        $complaint = Complaint::create(['tenant_id' => $tenant->id, 'category' => 'maintenance', 'description' => 'Water supply interrupted', 'status' => Complaint::STATUS_IN_PROGRESS]);
        $complaint->forceFill(['created_at' => now()->subDays(8)])->save();
        Complaint::create(['tenant_id' => $tenant->id, 'category' => 'noise', 'description' => 'Late night noise', 'status' => 'in_progress']);
        Complaint::create(['tenant_id' => $tenant->id, 'category' => 'other', 'description' => 'Resolved', 'status' => 'resolved']);
        $dashboard = $this->get(route('proprietor.dashboard'))->assertOk()->assertSee('Dashboard')->assertSee('Send SMS');
        $rooms = $this->get(route('proprietor.rooms.index'))->assertOk();
        $report = $this->get(route('proprietor.reports.index', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31']))->assertOk();
        $allReport = $this->get(route('proprietor.reports.index', ['date_from' => '2026-08-01', 'date_to' => '2026-10-31']))->assertOk();
        $dashboard->assertViewHas('totalBeds', $rooms->viewData('totalBeds'))->assertViewHas('occupiedBeds', $rooms->viewData('occupiedBeds'))
            ->assertViewHas('availableBeds', $rooms->viewData('vacantBeds'))
            ->assertViewHas('expectedThisMonth', $report->viewData('totalBilled'))
            ->assertViewHas('collectedThisMonth', $report->viewData('totalCollected'))
            ->assertViewHas('overdueBalance', $allReport->viewData('totalOverdue'))
            ->assertViewHas('openComplaints', 2)->assertViewHas('oldComplaints', 1)->assertViewHas('newComplaintsToday', 1)
            ->assertViewHas('followUps', fn ($rows) => $rows->first()->risk_level === 'high'
                && $rows->first()->rules['Overdue now']
                && $rows->first()->rules['Consecutive late months']
                && $rows->first()->rules['Repeated late payments']);
        Http::assertNothingSent();
        $this->assertDatabaseCount('sms_logs', 0);
    }

    public function test_follow_up_uses_accrued_rent_and_ages_pending_charges_without_sending_sms(): void
    {
        $this->admin();
        $tenant = $this->tenant();
        $room = Room::create(['room_number' => '101', 'floor' => '1', 'capacity' => 1, 'monthly_rate' => 1300]);
        $bed = $room->beds()->create(['bed_label' => 'A']);
        $tenant->roomAssignments()->create([
            'bed_id' => $bed->id, 'start_date' => '2026-08-01', 'status' => 'active',
        ]);
        $pending = $this->tenant('Pending charge');
        $pending->payments()->create(['amount' => 1200, 'due_date' => '2026-10-20', 'status' => 'pending', 'days_overdue' => 0]);

        $this->get(route('proprietor.dashboard'))->assertOk()->assertViewHas('followUps', fn ($rows) =>
            $rows->count() === 2
            && $rows->first()->tenant->is($tenant) && $rows->first()->consecutive_late_months === 2 // due Sep 1 and Oct 1
            && $rows->last()->tenant->is($pending) && $rows->last()->avg_days_overdue === 11.0
        )->assertViewHas('highRiskCount', 1);
        $this->assertDatabaseCount('payments', 1);
        Http::assertNothingSent();
    }

    public function test_settings_threshold_changes_immediately_change_follow_up_membership(): void
    {
        $this->admin();
        $tenant = $this->tenant();
        $this->late($tenant);
        $this->get(route('proprietor.dashboard'))->assertViewHas('highRiskCount', 1);
        $this->put(route('proprietor.settings.update'), [
            'dormitory_name' => 'La Grange', 'risk_late_payments' => 20, 'risk_consecutive_months' => 20,
            'risk_average_days' => 100, 'risk_recent_default_days' => 1,
        ])->assertRedirect();
        // The tenant stays in Medium follow-up: an overdue balance remains visible even when the High thresholds are raised.
        $this->get(route('proprietor.dashboard'))->assertViewHas('highRiskCount', 0)->assertViewHas('followUps', fn ($rows) => $rows->count() === 1);
        $this->put(route('proprietor.settings.update'), ['dormitory_name' => 'La Grange', 'risk_grace_days' => 60])->assertRedirect();
        $this->get(route('proprietor.dashboard'))->assertOk()->assertViewHas('followUps', fn ($rows) => $rows->count() === 1 && $rows->first()->risk_level === 'medium');
        $this->get(route('proprietor.settings.edit'))->assertOk()->assertSee('Payment risk thresholds');
    }

    public function test_manual_sms_goes_to_guardian_and_resends_are_idempotent(): void
    {
        $this->admin();
        $batch = 0;
        Http::fake(['api.textbee.dev/*' => function () use (&$batch) { // TextBee gives each message its own batch id
            return Http::response(['data' => ['success' => true, 'smsBatchId' => 'batch-'.(++$batch)]], 201);
        }]);
        $tenant = $this->tenant();
        $this->late($tenant);
        $this->postJson(route('proprietor.dashboard.sms', $tenant))->assertOk()->assertJsonPath('status', 'queued');
        $this->postJson(route('proprietor.dashboard.sms', $tenant))->assertOk();
        Http::assertSentCount(1);
        $this->assertDatabaseCount('sms_logs', 1);
        $this->assertDatabaseHas('sms_logs', ['tenant_id' => $tenant->id, 'recipient_type' => 'guardian', 'recipient_number' => '09998887777', 'status' => 'queued', 'textbee_batch_id' => 'batch-1']);
        $this->get(route('proprietor.dashboard'))->assertOk()->assertSee('SMS queued')->assertSee('Send reminder again');
        $previousId = \App\Models\SmsLog::sole()->id;
        $this->postJson(route('proprietor.dashboard.sms', $tenant), ['resend_after' => $previousId])
            ->assertOk()->assertJsonPath('status', 'queued');
        $this->postJson(route('proprietor.dashboard.sms', $tenant), ['resend_after' => $previousId])->assertOk();
        Http::assertSentCount(2);
        $this->assertDatabaseCount('sms_logs', 2);
        Http::assertSent(fn ($request) => $request['recipients'] === ['+639998887777']); // TextBee wants +63 format
        Http::assertNotSent(fn ($request) => $request['recipients'] === ['+639171234567']);

        $tenant->update(['guardian_contact_number' => null]);
        $this->postJson(route('proprietor.dashboard.sms', $tenant))->assertUnprocessable();
        $this->get(route('proprietor.dashboard'))->assertSee('Guardian number missing');
        Http::assertSentCount(2);
        $low = $this->tenant('Low risk');
        $this->postJson(route('proprietor.dashboard.sms', $low))->assertUnprocessable();
    }

    public function test_tenant_details_link_prefills_the_payment_form(): void
    {
        $this->admin();
        $tenant = $this->tenant();
        $this->tenant('Other tenant');
        $url = route('proprietor.payments.create', ['tenant_id' => $tenant->id]);

        $this->get(route('proprietor.tenants.show', $tenant))->assertOk()->assertSee($url)->assertSee('Record payment');
        $this->get($url)->assertOk()->assertViewHas('selectedTenantId', $tenant->id)
            ->assertSee('value="'.$tenant->id.'" selected', false);
    }

    public function test_failed_sms_is_logged_and_can_be_retried(): void
    {
        $this->admin();
        Http::fake(['api.textbee.dev/*' => Http::response([], 503)]);
        $tenant = $this->tenant();
        $this->late($tenant);
        $this->postJson(route('proprietor.dashboard.sms', $tenant))->assertStatus(502)->assertJsonPath('sent', false)
            ->assertJsonPath('message', 'SMS could not be sent. TextBee is temporarily unavailable.');
        $this->assertDatabaseHas('sms_logs', ['status' => 'failed', 'error_message' => 'TextBee is temporarily unavailable.']);
        $this->get(route('proprietor.dashboard'))->assertViewHas('followUps', fn ($rows) => ! $rows->first()->sms_sent);
    }

    public function test_summary_is_generated_only_on_request_and_saves_structured_counts(): void
    {
        $admin = $this->admin();
        config(["services.groq.key" => "test-key"]);
        $tenant = $this->tenant();
        $complaint = Complaint::create(['tenant_id' => $tenant->id, 'category' => 'maintenance', 'description' => 'No water supply', 'status' => Complaint::STATUS_IN_PROGRESS]);
        // Filed as maintenance, but the text is about a wrong charge: the AI puts it under Billing.
        $misfiled = Complaint::create(['tenant_id' => $tenant->id, 'category' => 'maintenance', 'description' => 'Sobra ang gi-charge sa akong bill', 'status' => Complaint::STATUS_IN_PROGRESS]);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'complaints' => [['id' => $complaint->id, 'category' => 'maintenance'], ['id' => $misfiled->id, 'category' => 'billing']],
            'issues' => [
                ['category' => 'maintenance', 'issue' => 'Water supply interruptions', 'detail' => 'No water supply.', 'complaint_ids' => [$complaint->id]],
                ['category' => 'billing', 'issue' => 'Overcharged bill', 'detail' => 'A tenant was charged too much.', 'complaint_ids' => [$misfiled->id]],
            ],
            'recommended_action' => 'Inspect the water supply.',
        ])]]]], 200)]);
        $this->get(route('proprietor.dashboard'))->assertOk()->assertSee('id="pd-generate-summary"', false)
            ->assertDontSee('No saved complaint summary')->assertDontSee('No complaint summary has been saved')
            ->assertDontSee('AI SUMMARY')->assertDontSee('AI-generated')
            ->assertDontSee('Review before taking action.');
        Http::assertNothingSent();
        $this->postJson(route('proprietor.dashboard.summary'))->assertOk();
        $this->assertDatabaseCount('ai_summaries', 1);
        $this->assertSame($admin->id, AiSummary::sole()->generated_by);
        $this->get(route('proprietor.dashboard'))->assertOk()->assertSee('Water supply interruptions')->assertSee('Inspect the water supply.')
            ->assertSeeInOrder(['Maintenance', 'Water supply interruptions', 'Billing', 'Overcharged bill'])
            ->assertSee('1 complaint filed under a different category.')
            ->assertSee("Complaint #{$misfiled->id}</a>: filed as Maintenance, reads as Billing", false)
            ->assertSee('Review before taking action.');
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['model'] === config('services.groq.model')
            && $request['response_format'] === ['type' => 'json_object']);
    }

    public function test_summary_api_failure_keeps_the_previous_summary(): void
    {
        $admin = $this->admin();
        config(["services.groq.key" => "test-key"]);
        $tenant = $this->tenant();
        AiSummary::create(['generated_by' => $admin->id, 'filter_params' => [], 'prompt_used' => 'Legacy prompt', 'summary_result' => 'Existing saved summary', 'response_time_seconds' => 1]);
        Complaint::create(['tenant_id' => $tenant->id, 'category' => 'other', 'description' => 'One complaint', 'status' => Complaint::STATUS_IN_PROGRESS]);
        Http::fake(['api.groq.com/*' => Http::response(['error' => 'unavailable'], 503)]);
        $this->postJson(route('proprietor.dashboard.summary'))->assertStatus(502);
        $this->assertDatabaseCount('ai_summaries', 1);
        $this->get(route('proprietor.dashboard'))->assertOk()->assertSee('Existing saved summary');
    }

    public function test_reserved_rooms_notifications_and_risk_history_come_from_payment_history(): void
    {
        $admin = $this->admin();
        $tenant = $this->tenant();
        $this->late($tenant);
        // The history comes from the payment record (overdue since Oct 1), not from saved flags.
        Room::create(['room_number' => '201', 'floor' => '2', 'capacity' => 1, 'monthly_rate' => 1300, 'status' => 'reserved']);
        $admin->notifications()->create(['type' => 'announcement', 'message' => 'Test', 'is_read' => false]);
        $this->get(route('proprietor.dashboard'))->assertOk()->assertViewHas('reservedRooms', 1)->assertViewHas('unreadCount', 1)
            ->assertSee('Payment status each month: Sep overdue, Oct overdue')->assertViewHas('upcomingMoveIns', 0);
    }

    public function test_guest_and_tenant_cannot_access_dashboard_or_actions(): void
    {
        $tenant = $this->tenant();
        $this->get(route('proprietor.dashboard'))->assertRedirect(route('login'));
        $this->actingAs($tenant->user)->get(route('proprietor.dashboard'))->assertForbidden();
        $this->postJson(route('proprietor.dashboard.sms', $tenant))->assertForbidden();
        $this->postJson(route('proprietor.dashboard.summary'))->assertForbidden();
    }

    public function test_fifty_tenant_dashboard_has_bounded_queries_and_loads_under_three_seconds(): void
    {
        $this->admin();
        $room = Room::create(['room_number' => 'PERF', 'floor' => '1', 'capacity' => 50, 'monthly_rate' => 1300]);
        for ($index = 0; $index < 50; $index++) {
            $tenant = $this->tenant('Tenant '.$index);
            $bed = Bed::create(['room_id' => $room->id, 'bed_label' => 'Bed '.$index, 'status' => 'available']);
            RoomAssignment::create(['tenant_id' => $tenant->id, 'bed_id' => $bed->id, 'start_date' => '2026-08-01', 'status' => 'active']);
            $this->late($tenant);
        }
        $this->get(route('proprietor.dashboard'))->assertOk(); // warm-up: compiles views once, like any page after a deploy
        DB::enableQueryLog();
        $started = microtime(true);
        $this->get(route('proprietor.dashboard'))->assertOk()->assertViewHas('occupiedBeds', 50)->assertViewHas('highRiskCount', 50);
        $elapsed = microtime(true) - $started;
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThan(3.0, $elapsed, '50-tenant dashboard render exceeded 3 seconds.');
        $this->assertLessThan(40, $queries, 'Dashboard has unexpected per-tenant queries.');
        Http::assertNothingSent();
    }

    /** A tenant with a room, moved in on $moveIn, paying 1,300 a month. */
    private function resident(string $name, string $moveIn, string $roomNumber): Tenant
    {
        $tenant = Tenant::create(['user_id' => User::factory()->create(['name' => $name, 'role' => 'tenant'])->id, 'status' => 'active', 'move_in_date' => $moveIn]);
        $room = Room::create(['room_number' => $roomNumber, 'floor' => '1', 'capacity' => 1, 'monthly_rate' => 1300]);
        $bed = Bed::create(['room_id' => $room->id, 'bed_label' => 'A', 'status' => 'available']);
        RoomAssignment::create(['tenant_id' => $tenant->id, 'bed_id' => $bed->id, 'start_date' => $moveIn, 'status' => 'active']);

        return $tenant;
    }

    public function test_alert_tiles_show_all_clear_and_then_the_urgent_items_first(): void
    {
        $this->admin();
        $this->get(route('proprietor.dashboard'))->assertOk()
            ->assertSee('No overdue charges')->assertSee('Urgent complaints')->assertSee('None right now')
            ->assertSee('Nothing due this week')
            ->assertDontSee('Top complaint')->assertDontSee('Complaints waiting') // the summary card covers the top issue
            ->assertViewHas('overdueTenants', 0);

        $late = $this->resident('Late Payer', '2026-08-01', '301');
        $this->late($late);
        Complaint::create(['tenant_id' => $late->id, 'category' => 'maintenance', 'description' => 'Leak', 'status' => Complaint::STATUS_IN_PROGRESS])
            ->forceFill(['created_at' => now()->subDays(12)])->save();
        $soon = $this->resident('Due Soon', '2026-09-03', '302'); // rent due Nov 3, three days away
        Payment::create(['tenant_id' => $soon->id, 'amount' => 1300, 'due_date' => '2026-10-03', 'paid_date' => '2026-10-03', 'status' => 'paid', 'payment_type' => 'full']);

        $response = $this->get(route('proprietor.dashboard'))->assertOk()
            ->assertViewHas('overdueTenants', 1)
            ->assertViewHas('oldestOpenComplaintDays', 12)
            ->assertViewHas('dueSoon', fn ($rows) => $rows->map(fn ($r) => [$r->tenant->id, $r->due_date->toDateString(), $r->amount])->all()
                === [[$late->id, '2026-11-01', 1300.0], [$soon->id, '2026-11-03', 1300.0]]) // soonest first
            ->assertSee('Review overdue')->assertSee('0 urgent · 1 waiting 7+ days (oldest 12 days)')->assertSee('Record payment')
            ->assertDontSee('No overdue charges');
        // The overdue alert (danger) comes before the complaints tile (warning).
        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'Urgent complaints'), strpos($html, 'Tenants with overdue charges'));
    }

    public function test_an_ai_flagged_urgent_complaint_turns_the_complaints_tile_red_and_shows_why(): void
    {
        $this->admin();
        $tenant = $this->tenant();
        $urgent = Complaint::create(['tenant_id' => $tenant->id, 'category' => 'maintenance', 'description' => 'Nag-spark ang outlet', 'status' => Complaint::STATUS_IN_PROGRESS]);
        $urgent->forceFill(['priority' => 'urgent', 'priority_reason' => 'Sparking outlet is a fire risk'])->save();
        Complaint::create(['tenant_id' => $tenant->id, 'category' => 'noise', 'description' => 'Loud music', 'status' => Complaint::STATUS_IN_PROGRESS])
            ->forceFill(['priority' => 'normal'])->save();

        $this->get(route('proprietor.dashboard'))->assertOk()
            ->assertViewHas('attentionComplaintIds', [$urgent->id])
            ->assertSee('Sparking outlet is a fire risk')
            ->assertSee('1 urgent · 0 waiting 7+ days')
            ->assertSee('pd-tile-danger', false);
    }

    public function test_dashboard_keeps_the_payment_history_and_removes_aggregate_trend_charts(): void
    {
        $this->admin();
        $tenant = $this->resident('Payment Pattern', '2026-05-10', '401');
        foreach ([
            ['2026-05-10', '2026-05-15'], // 5 days late, within the grace period: on time for risk
            ['2026-06-10', '2026-06-10'], // paid on time
            ['2026-07-10', '2026-07-16'], // paid late (past the 5-day grace period)
            ['2026-08-10', null], // overdue
        ] as [$due, $paid]) {
            Payment::create(['tenant_id' => $tenant->id, 'amount' => 1300, 'due_date' => $due, 'paid_date' => $paid, 'status' => $paid ? 'paid' : 'overdue', 'payment_type' => 'full']);
        }

        $this->get(route('proprietor.dashboard'))->assertOk()
            ->assertViewHas('followUps', function ($rows) use ($tenant) {
                $row = $rows->firstWhere(fn ($item) => $item->tenant->is($tenant));

                return $row !== null
                    && $row->trend->firstWhere('label', 'May')['status'] === 'on_time'
                    && $row->trend->firstWhere('label', 'Jun')['status'] === 'on_time'
                    && $row->trend->firstWhere('label', 'Jul')['status'] === 'late'
                    && $row->trend->firstWhere('label', 'Aug')['status'] === 'overdue';
            })
            ->assertSee('Paid on time')->assertSee('Paid late')->assertSee('Overdue')
            ->assertDontSee('Collections trend')->assertDontSee('Payment risk over time')
            ->assertDontSee('pd-collections-chart', false)->assertDontSee('pd-risk-chart', false);
    }

    public function test_follow_up_list_explains_why_in_plain_words_with_amount_owed_and_history(): void
    {
        $this->admin();
        // Moved in Jul 20: rent due Aug 20, Sep 20 and Oct 20, none paid. Today is Oct 31.
        $behind = $this->resident('Three Behind', '2026-07-20', '601');
        $soon = $this->resident('Due Soon', '2026-09-03', '602');
        Payment::create(['tenant_id' => $soon->id, 'amount' => 1300, 'due_date' => '2026-10-03', 'paid_date' => '2026-10-03', 'status' => 'paid', 'payment_type' => 'full']);

        $this->get(route('proprietor.dashboard'))->assertOk()
            ->assertViewHas('followUps', function ($rows) use ($behind) {
                $row = $rows->firstWhere(fn ($r) => $r->tenant->is($behind));

                return $rows->count() === 1 && $row->owes === 3900.0 && $row->reasons === [
                    'Has an overdue balance right now',
                    'Late 3 times in the last 6 months',
                    'Late 3 months in a row',
                    'Usually pays about 41 days late',
                ];
            })
            // One main reason under the name, every reason on the badge, the overdue balance under the amount.
            ->assertSee('<span class="pd-main-reason">Late 3 months in a row</span>', false)
            ->assertSee("Why flagged:&#10;• Has an overdue balance right now\n• Late 3 times in the last 6 months\n• Late 3 months in a row\n• Usually pays about 41 days late", false)
            ->assertSee('Monthly boxes show payment status')
            ->assertSee('₱3,900.00')->assertSee('overdue now')
            ->assertSee('Payment status each month: Aug overdue, Sep overdue, Oct overdue')
            ->assertSee('id="pd-follow-filters"', false)->assertSee('Search name or room')
            ->assertSee('Rent due in 7 days')->assertSee('next Due Soon, Nov 3')->assertDontSee('Rent due in the next 7 days');
    }

    public function test_dashboard_has_no_occupancy_by_floor_or_complaints_by_category_section(): void
    {
        $this->admin();
        // The Complaint Summary already groups complaints by (AI-checked) category, so there is no separate chart.
        $this->get(route('proprietor.dashboard'))->assertOk()
            ->assertDontSee('Occupancy by floor')->assertViewMissing('floors')
            ->assertDontSee('Complaints by category')->assertViewMissing('complaintsByCategory')
            ->assertSee('Complaint Summary');
    }

    public function test_monthly_payment_history_updates_when_a_payment_is_recorded(): void
    {
        $this->admin();
        $tenant = $this->resident('Paid Late', '2026-08-15', '701'); // rent due Sep 15 and Oct 15
        $septemberStatus = fn () => $this->get(route('proprietor.dashboard'))->viewData('followUps')
            ->first(fn ($row) => $row->tenant->is($tenant))->trend->firstWhere('label', 'Sep')['status'];

        $this->assertSame('overdue', $septemberStatus());
        // Paid on the due date, recorded today: the ledger should show September as paid on time.
        Payment::create(['tenant_id' => $tenant->id, 'amount' => 1300, 'due_date' => '2026-09-15', 'paid_date' => '2026-09-15', 'status' => 'paid', 'payment_type' => 'full']);
        $this->assertSame('on_time', $septemberStatus());
    }

    public function test_follow_up_list_shows_an_all_clear_message_without_filters(): void
    {
        $this->admin();
        $this->get(route('proprietor.dashboard'))->assertOk()
            ->assertSee('No tenants need a payment follow-up right now.')
            ->assertSee('Nothing due this week')
            ->assertDontSee('id="pd-follow-filters"', false);
    }

    public function test_view_all_opens_the_ledger_and_at_risk_tenants_toggle_filters_it(): void
    {
        $this->admin();
        $risky = $this->resident('Risky Payer', '2026-08-01', '501');
        $this->late($risky);
        $fine = $this->resident('Fine Payer', '2026-09-01', '502'); // first rent due Oct 1, paid on time
        Payment::create(['tenant_id' => $fine->id, 'amount' => 1300, 'due_date' => '2026-10-01', 'paid_date' => '2026-10-01', 'status' => 'paid', 'payment_type' => 'full']);

        $this->get(route('proprietor.dashboard'))->assertOk()
            ->assertSee('href="'.route('proprietor.payments.at-risk').'"', false)->assertSee('View all')
            ->assertSee('data-per-page="5"', false);

        // Same follow-up list as the dashboard, just more rows per page and no "View all".
        $this->get(route('proprietor.payments.at-risk'))->assertOk()
            ->assertViewHas('followUps', fn ($rows) => $rows->map(fn ($row) => $row->tenant->id)->all() === [$risky->id])
            ->assertSee('Tenants to follow up')->assertSee('Risky Payer')->assertDontSee('Fine Payer')
            ->assertSee('pd-history-cell', false)->assertSee('data-per-page="20"', false)->assertDontSee('View all');
        $this->get(route('proprietor.payments.index'))->assertOk()
            ->assertSee('href="'.route('proprietor.payments.at-risk').'"', false)->assertSee('At Risk Tenants');
    }
}
