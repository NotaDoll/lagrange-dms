<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use App\Models\TenantMonthMode;
use App\Models\User;
use App\Services\RoomAvailability;
use App\Services\SettingsService;
use App\Services\TenantBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SummerStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 8, 15)->setTime(12, 0));
    }

    private function room(): Room
    {
        return Room::create(['room_number' => 'CABIN 1', 'floor' => '1', 'capacity' => 3, 'monthly_rate' => 3000]);
    }

    private function tenant(Room $room, string $bedLabel, string $moveIn, array $attributes = []): Tenant
    {
        $tenant = Tenant::factory()->create($attributes + ['move_in_date' => $moveIn, 'status' => Tenant::STATUS_ACTIVE]);
        $bed = $room->beds()->create(['bed_label' => $bedLabel]);
        RoomAssignment::create(['tenant_id' => $tenant->id, 'bed_id' => $bed->id, 'start_date' => $moveIn, 'status' => 'active']);

        return $tenant;
    }

    public function test_cutoff_preserves_real_dates_and_bills_from_the_move_in_day(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'proprietor']));
        $this->put(route('proprietor.settings.update'), [
            'dormitory_name' => 'Dorm', 'billing_start_date' => '2026-06-10',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2026-06-10', app(SettingsService::class)->get('general', 'billing_start_date'));
        $room = $this->room();
        $longStay = $this->tenant($room, 'A', '2021-06-15');
        $new = $this->tenant($room, 'B', '2026-06-16');
        $reserved = $this->tenant($room, 'C', '2026-06-16', ['status' => Tenant::STATUS_RESERVED]);
        $historical = $longStay->payments()->create(['amount' => 150, 'due_date' => '2026-05-31', 'status' => 'paid']);
        $balances = app(TenantBalanceService::class);

        $this->assertSame('2021-06-15', $longStay->fresh()->move_in_date->toDateString());
        $entries = $balances->billingEntries($longStay, '2026-05-01', '2026-06-30');
        $this->assertSame(['150.00', '3000.00'], $entries->pluck('amount')->all());
        $this->assertSame($historical->id, $entries->first()->id);
        // The cutoff keeps established tenants' billing dates. The new tenant owes their first rent in July.
        $this->assertSame('2026-06-15', $entries[1]->due_date->toDateString());
        $this->assertTrue($balances->billingEntries($new, '2026-06-01', '2026-06-30')->isEmpty());
        $july = $balances->billingEntries($new, '2026-07-01', '2026-07-31')->sole();
        $this->assertSame(['3000.00', '2026-07-16'], [$july->amount, $july->due_date->toDateString()]);
        $this->assertCount(0, $balances->billingEntries($reserved, '2026-06-01', '2026-06-30'));
    }

    public function test_retroactive_storage_regenerates_unpaid_rent_and_keeps_paid_rent_and_the_bed(): void
    {
        $room = $this->room();
        $user = User::factory()->create(['role' => 'tenant']);
        $tenant = $this->tenant($room, 'A', '2026-06-16', ['user_id' => $user->id]);
        $late = $this->tenant($room, 'B', '2026-08-01');
        $june = $tenant->payments()->create(['amount' => 1500, 'due_date' => '2026-06-30', 'status' => 'pending']);
        $july = $tenant->payments()->create(['amount' => 100, 'due_date' => '2026-07-31', 'status' => 'paid']);
        $this->actingAs(User::factory()->create(['role' => 'proprietor']));
        $url = route('proprietor.summer-storage.store');
        $this->get(route('proprietor.summer-storage.index'))->assertOk()
            ->assertSee('June 2026')->assertSee('July 2026')->assertSee('Select all');
        $this->post($url, [
            'year' => 2026, 'months' => [6, 7], 'action' => 'storage', 'tenants' => [$tenant->id, $late->id],
        ])->assertSessionHas('storage_result', fn ($result) => $result['updated'] === 2 && count($result['warnings']) === 3);
        $this->assertSame('200.00', $june->fresh()->amount);
        $this->assertSame('Storage Fee - June 2026', $june->fresh()->description);
        $this->assertSame('100.00', $july->fresh()->amount);
        $this->assertSame(Payment::RENT, $july->fresh()->category);
        $this->assertSame(['100.00'], app(TenantBalanceService::class)
            ->billingEntries($tenant->fresh(), '2026-07-01', '2026-07-31')->pluck('amount')->all());
        $this->assertNotContains($tenant->currentAssignment->bed_id, collect(app(RoomAvailability::class)->forTenant()['CABIN']['rooms'])
            ->flatMap(fn ($entry) => array_column($entry['beds'], 'id'))->all());

        $this->post($url, [
            'year' => 2026, 'months' => [6, 7], 'action' => 'amount', 'tenant_id' => $tenant->id, 'amount_month' => 6, 'amount' => 275,
        ])->assertSessionHasNoErrors();
        $this->assertSame('275.00', $june->fresh()->amount);
        $this->patch(route('proprietor.payments.settle', $june), ['paid_date' => '2026-08-15', 'receipt_number' => 'OR-504'])->assertRedirect();
        $this->get(route('proprietor.payments.index', ['category' => Payment::STORAGE]))->assertOk()->assertSee('Storage Fee - June 2026');
        $this->get(route('proprietor.reports.index', ['date_from' => '2026-06-01', 'date_to' => '2026-07-31']))
            ->assertOk()->assertViewHas('incomeCategories', fn ($rows) => $rows[Payment::STORAGE]['collected'] === 275.0
                && $rows[Payment::RENT]['collected'] === 100.0);

        $this->post($url, [
            'year' => 2026, 'months' => [6, 7], 'action' => 'amount', 'tenant_id' => $tenant->id, 'amount_month' => 6, 'amount' => 400,
        ])->assertSessionHas('storage_result', fn ($result) => count($result['warnings']) === 1);
        $this->assertSame('275.00', $june->fresh()->amount);
        $this->assertSame(['275.00'], app(TenantBalanceService::class)
            ->billingEntries($tenant->fresh(), '2026-06-01', '2026-06-30')->pluck('amount')->all());

        $this->actingAs($tenant->user)->get(route('tenant.profile.show'))->assertOk()->assertSee('Storage months')->assertSee('June 2026');
        $this->get(route('proprietor.summer-storage.index'))->assertForbidden();
        $this->post($url, [])->assertForbidden();
    }

    public function test_new_utility_shares_skip_storage_and_can_be_enabled_by_config(): void
    {
        $room = $this->room();
        $storage = $this->tenant($room, 'A', '2026-01-01');
        $regular = $this->tenant($room, 'B', '2026-01-01');
        $storage->monthModes()->create(['month' => '2026-06-01', 'mode' => TenantMonthMode::STORAGE]);
        $this->actingAs(User::factory()->create(['role' => 'proprietor']));
        $url = route('proprietor.utility-bills.store');
        $attributes = [
            'room_id' => $room->id, 'bill_type' => 'water', 'billing_period_start' => '2026-06-01',
            'billing_period_end' => '2026-06-30', 'due_date' => '2026-07-10', 'amount' => 400, 'status' => 'pending',
        ];
        $this->post($url, $attributes)->assertSessionHasNoErrors();
        $this->assertSame('400.00', $regular->payments()->sole()->amount);
        $this->assertFalse($storage->payments()->exists());

        $this->post($url, [
            'billing_period_start' => '2026-06-30', 'billing_period_end' => '2026-07-01',
            'amount' => 400,
        ] + $attributes)->assertSessionHasNoErrors();
        $this->assertSame('100.00', $storage->payments()->sole()->amount);
        $this->assertSame('300.00', $regular->payments()->latest('id')->firstOrFail()->amount);

        config(['dorm.storage_skips_utilities' => false]);
        $this->post($url, $attributes)->assertSessionHasNoErrors();
        $this->assertSame('200.00', $storage->payments()->latest('id')->firstOrFail()->amount);

        config(['dorm.storage_skips_utilities' => true]);
        $regular->monthModes()->create(['month' => '2026-06-01', 'mode' => TenantMonthMode::STORAGE]);
        $this->post($url, $attributes)->assertSessionHasNoErrors();
        $providerBill = \App\Models\UtilityBill::latest('id')->firstOrFail();
        $this->assertFalse($providerBill->payments()->exists());

        $this->put(route('proprietor.utility-bills.update', $providerBill), $attributes + ['notes' => 'Provider bill'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($providerBill->payments()->exists());
    }
}
