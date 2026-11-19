<?php

namespace Tests\Feature;

use App\Models\AiSummary;
use App\Models\Bed;
use App\Models\Complaint;
use App\Models\Payment;
use App\Models\RiskFlag;
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
            ->assertViewHas('followUps', fn ($rows) => $rows->first()->rules_tripped === 4 && $rows->first()->risk_level === 'high');
        Http::assertNothingSent();
        $this->assertDatabaseCount('risk_flags', 0);
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
        )->assertViewHas('highRiskCount', 2);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('risk_flags', 0);
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
        // Still flagged: the Oct 1 charge is 30 days unpaid, and an overdue balance always trips "Recent default".
        $this->get(route('proprietor.dashboard'))->assertViewHas('highRiskCount', 0)->assertViewHas('followUps', fn ($rows) => $rows->count() === 1);
        $this->put(route('proprietor.settings.update'), ['dormitory_name' => 'La Grange', 'risk_grace_days' => 60])->assertRedirect();
        $this->get(route('proprietor.dashboard'))->assertOk()->assertViewHas('followUps', fn ($rows) => $rows->isEmpty());
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
        $this->get(route('proprietor.dashboard'))->assertOk()->assertSee('SMS queued')->assertSee('Send SMS again');
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
        Complaint::create(['tenant_id' => $tenant->id, 'category' => 'maintenance', 'description' => 'No water supply', 'status' => Complaint::STATUS_IN_PROGRESS]);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['themes' => [['theme' => 'Water supply interruptions', 'count' => 1]], 'recommended_action' => 'Inspect the water supply.'])]]]], 200)]);
        $this->get(route('proprietor.dashboard'))->assertOk()->assertSee('id="pd-generate-summary"', false)
            ->assertDontSee('No saved complaint summary')->assertDontSee('No complaint summary has been saved')
            ->assertDontSee('AI SUMMARY')->assertDontSee('AI-generated')
            ->assertDontSee('Review before taking action.');
        Http::assertNothingSent();
        $this->postJson(route('proprietor.dashboard.summary'))->assertOk();
        $this->assertDatabaseCount('ai_summaries', 1);
        $this->assertSame($admin->id, AiSummary::sole()->generated_by);
        $this->get(route('proprietor.dashboard'))->assertOk()->assertSee('Water supply interruptions')->assertSee('Inspect the water supply.')
            ->assertSee('Review before taking action.');
        Http::assertSentCount(1);
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
        // A saved flag no longer drives the history; the payment record does (overdue since Oct 1).
        RiskFlag::create(['tenant_id' => $tenant->id, 'risk_level' => 'medium', 'calculated_at' => '2026-09-01']);
        Room::create(['room_number' => '201', 'floor' => '2', 'capacity' => 1, 'monthly_rate' => 1300, 'status' => 'reserved']);
        $admin->notifications()->create(['type' => 'announcement', 'message' => 'Test', 'is_read' => false]);
        $this->get(route('proprietor.dashboard'))->assertOk()->assertViewHas('reservedRooms', 1)->assertViewHas('unreadCount', 1)
            ->assertSee('Sep: not billed, Oct: high')->assertDontSee('Sep: medium')->assertViewHas('upcomingMoveIns', 0);
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
            ->assertSee('No high-risk tenants')->assertSee('None waiting over 7 days')
            ->assertSee('Nothing due this week')->assertSee('No summary yet')
            ->assertViewHas('riskCounts', ['high' => 0, 'medium' => 0, 'low' => 0]);

        $late = $this->resident('Late Payer', '2026-08-01', '301');
        $this->late($late);
        Complaint::create(['tenant_id' => $late->id, 'category' => 'maintenance', 'description' => 'Leak', 'status' => Complaint::STATUS_IN_PROGRESS])
            ->forceFill(['created_at' => now()->subDays(12)])->save();
        $soon = $this->resident('Due Soon', '2026-09-03', '302'); // rent due Nov 3, three days away
        Payment::create(['tenant_id' => $soon->id, 'amount' => 1300, 'due_date' => '2026-10-03', 'paid_date' => '2026-10-03', 'status' => 'paid', 'payment_type' => 'full']);

        $response = $this->get(route('proprietor.dashboard'))->assertOk()
            ->assertViewHas('riskCounts', fn ($counts) => $counts['high'] === 1)
            ->assertViewHas('oldestOpenComplaintDays', 12)
            ->assertViewHas('dueSoon', fn ($rows) => $rows->map(fn ($r) => [$r->tenant->id, $r->due_date->toDateString(), $r->amount])->all()
                === [[$late->id, '2026-11-01', 1300.0], [$soon->id, '2026-11-03', 1300.0]]) // soonest first
            ->assertSee('Follow up')->assertSee('Review')->assertSee('Record payment')
            ->assertDontSee('No high-risk tenants');
        // The high-risk tile (danger) comes before the complaints tile (warning).
        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'Complaints waiting'), strpos($html, 'High-risk tenants'));
    }

    public function test_trends_show_collections_and_risk_for_each_month_from_payment_history(): void
    {
        $this->admin();
        // Moved in May 10: rent due on the 10th from June, always paid on the day.
        $onTime = $this->resident('On Time', '2026-05-10', '401');
        foreach (['06', '07', '08', '09', '10'] as $m) {
            Payment::create(['tenant_id' => $onTime->id, 'amount' => 1300, 'due_date' => "2026-$m-10", 'paid_date' => "2026-$m-10", 'status' => 'paid', 'payment_type' => 'full']);
        }
        // Moved in October: not counted in earlier months.
        $this->resident('Newcomer', '2026-10-05', '402');

        $this->get(route('proprietor.dashboard'))->assertOk()
            ->assertViewHas('trendMonths', 6)
            ->assertViewHas('collectionsTrend', function ($months) {
                return count($months) === 6 && $months[0]['label'] === 'May 2026' && end($months)['label'] === 'Oct 2026'
                    && end($months)['expected'] === 1300.0 && end($months)['collected'] === 1300.0;
            })
            ->assertViewHas('riskTrend', function ($months) {
                $byLabel = collect($months)->keyBy('label');

                return $byLabel['Sep 2026'] === ['label' => 'Sep 2026', 'high' => 0, 'medium' => 0, 'low' => 1]
                    && $byLabel['Oct 2026'] === ['label' => 'Oct 2026', 'high' => 0, 'medium' => 0, 'low' => 2];
            })
            ->assertSee('Payment risk over time')->assertSee('Show the numbers');

        $this->get(route('proprietor.dashboard', ['trend' => 12]))->assertViewHas('collectionsTrend', fn ($m) => count($m) === 12);
        $this->get(route('proprietor.dashboard', ['trend' => 99]))->assertViewHas('trendMonths', 6);
    }

    public function test_risk_trend_counts_a_payment_as_unpaid_until_the_day_it_was_made(): void
    {
        $this->admin();
        $tenant = $this->resident('Paid Late', '2026-07-20', '501'); // rent due Aug 20, Sep 20, Oct 20
        foreach (['08', '09'] as $m) {
            // Both paid on Oct 25, long after they were due.
            Payment::create(['tenant_id' => $tenant->id, 'amount' => 1300, 'due_date' => "2026-$m-20", 'paid_date' => '2026-10-25', 'status' => 'paid', 'payment_type' => 'full']);
        }
        Payment::create(['tenant_id' => $tenant->id, 'amount' => 1300, 'due_date' => '2026-10-20', 'paid_date' => '2026-10-20', 'status' => 'paid', 'payment_type' => 'full']);

        $this->get(route('proprietor.dashboard'))->assertOk()->assertViewHas('riskTrend', function ($months) {
            $byLabel = collect($months)->keyBy('label');

            // End of September: August and September were both still unpaid, so the tenant was high risk then.
            return $byLabel['Sep 2026']['high'] === 1 && $byLabel['Jul 2026']['low'] === 1;
        });
    }

    public function test_twelve_month_trend_with_fifty_tenants_loads_under_three_seconds(): void
    {
        $this->admin();
        $room = Room::create(['room_number' => 'PERF', 'floor' => '1', 'capacity' => 50, 'monthly_rate' => 1300]);
        for ($index = 0; $index < 50; $index++) {
            $tenant = $this->tenant('Tenant '.$index);
            $bed = Bed::create(['room_id' => $room->id, 'bed_label' => 'Bed '.$index, 'status' => 'available']);
            RoomAssignment::create(['tenant_id' => $tenant->id, 'bed_id' => $bed->id, 'start_date' => '2026-08-01', 'status' => 'active']);
            $this->late($tenant);
        }
        $this->get(route('proprietor.dashboard', ['trend' => 12]))->assertOk(); // warm-up
        DB::enableQueryLog();
        $started = microtime(true);
        $this->get(route('proprietor.dashboard', ['trend' => 12]))->assertOk()->assertViewHas('riskTrend', fn ($m) => count($m) === 12);
        $elapsed = microtime(true) - $started;
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThan(3.0, $elapsed, '12-month trend with 50 tenants exceeded 3 seconds.');
        $this->assertLessThan(60, $queries, 'Trend charts added per-tenant queries.');
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
                    'Late in 3 months of the last 6',
                    'Late 3 months in a row',
                    'Pays 41.3 days late on average',
                    'Has an overdue balance now',
                ];
            })
            ->assertSee('Why flagged')->assertSee('₱3,900.00')
            ->assertSee('Jul: low, Aug: high, Sep: high, Oct: high')
            ->assertSee('id="pd-follow-filters"', false)->assertSee('Search name or room')
            ->assertSee('Rent due in the next 7 days')->assertSee('Tue, Nov 3')->assertSee('in 3 days');
    }

    public function test_complaints_by_category_count_open_and_resolved_within_the_trend_period(): void
    {
        $this->admin();
        $tenant = $this->tenant();
        $make = fn (string $category, string $status, string $created) => Complaint::create(['tenant_id' => $tenant->id, 'category' => $category, 'description' => 'x', 'status' => $status])
            ->forceFill(['created_at' => $created])->save();
        $make('maintenance', Complaint::STATUS_IN_PROGRESS, '2026-10-20');
        $make('maintenance', Complaint::STATUS_RESOLVED, '2026-09-02');
        $make('noise', Complaint::STATUS_IN_PROGRESS, '2026-08-15');
        $make('billing', Complaint::STATUS_RESOLVED, '2026-01-10'); // older than 6 months

        $this->get(route('proprietor.dashboard'))->assertOk()
            ->assertViewHas('complaintsByCategory', fn ($rows) => array_slice($rows, 0, 2) === [
                ['category' => 'maintenance', 'label' => 'Maintenance', 'open' => 1, 'resolved' => 1],
                ['category' => 'noise', 'label' => 'Noise', 'open' => 1, 'resolved' => 0],
            ] && collect($rows)->firstWhere('category', 'billing')['resolved'] === 0)
            ->assertSee('Complaints by category')->assertSee('3 in total');
        $this->get(route('proprietor.dashboard', ['trend' => 12]))
            ->assertViewHas('complaintsByCategory', fn ($rows) => collect($rows)->firstWhere('category', 'billing')['resolved'] === 1);
    }

    public function test_dashboard_has_no_occupancy_by_floor_section(): void
    {
        $this->admin();
        $this->get(route('proprietor.dashboard'))->assertOk()
            ->assertDontSee('Occupancy by floor')->assertViewMissing('floors')
            ->assertSee('Complaints by category')->assertSee('Complaint Summary');
    }

    public function test_remembered_risk_history_refreshes_as_soon_as_a_payment_is_recorded(): void
    {
        $this->admin();
        $tenant = $this->resident('Paid Late', '2026-08-15', '701'); // rent due Sep 15 and Oct 15
        $septemberLevel = fn () => collect($this->get(route('proprietor.dashboard'))->viewData('riskTrend'))->firstWhere('label', 'Sep 2026');

        $this->assertSame(1, $septemberLevel()['medium'] + $septemberLevel()['high']); // Sep 15 unpaid at Sep 30
        // Paid on the due date, recorded today: September was on time after all.
        Payment::create(['tenant_id' => $tenant->id, 'amount' => 1300, 'due_date' => '2026-09-15', 'paid_date' => '2026-09-15', 'status' => 'paid', 'payment_type' => 'full']);
        $this->assertSame(['label' => 'Sep 2026', 'high' => 0, 'medium' => 0, 'low' => 1], $septemberLevel());
    }

    public function test_follow_up_list_shows_an_all_clear_message_without_filters(): void
    {
        $this->admin();
        $this->get(route('proprietor.dashboard'))->assertOk()
            ->assertSee('No tenants need a payment follow-up right now.')
            ->assertSee('Nothing is due this week.')
            ->assertDontSee('id="pd-follow-filters"', false);
    }
}
