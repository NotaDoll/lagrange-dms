<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantManagementTest extends TestCase
{
    use RefreshDatabase;

    private function bed(string $label = 'A'): Bed
    {
        $room = Room::firstOrCreate(['room_number' => '305'], ['floor' => '3', 'capacity' => 2, 'monthly_rate' => 5000]);

        return Bed::create(['room_id' => $room->id, 'bed_label' => $label, 'status' => 'available']);
    }

    private function form(Bed $bed, array $overrides = []): array
    {
        return array_replace([
            'first_name' => 'Ana',
            'middle_name' => 'Santos',
            'last_name' => 'De la Peña-Cruz',
            'email' => ' ANA@example.com ',
            'contact_number' => '+63 917-123-4567',
            'home_address' => '123 Main Street',
            'bed_id' => $bed->id,
            'status' => Tenant::STATUS_RESERVED,
            'security_deposit' => '500.00',
            'advance_payment' => '100.00',
            'emergency_contact_name' => 'Maria Cruz',
            'emergency_contact_number' => '639988765432',
            'emergency_contact_relationship' => 'mother',
            'data_privacy_consent' => '1',
        ], $overrides);
    }

    public function test_creating_a_reservation_holds_the_bed_and_records_consent_without_exposing_password(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(12, 0));
        $proprietor = User::factory()->create(['role' => 'proprietor']);
        $bed = $this->bed();

        $this->actingAs($proprietor)->post(route('proprietor.tenants.store'), $this->form($bed, ['move_in_date' => '2026-12-21']))
            ->assertRedirect(route('proprietor.tenants.index'))
            ->assertSessionHas('success', 'Tenant created successfully.')
            ->assertSessionMissing('temp_password');

        $user = User::where('email', 'ana@example.com')->sole();
        $tenant = $user->tenant()->sole();
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check('DELAPENACRUZ09171234567', $user->password));
        $this->assertSame('09171234567', $user->contact_number);
        $this->assertSame('09988765432', $tenant->emergency_contact_number);
        $this->assertSame('123 Main Street', $tenant->home_address);
        $this->assertSame('mother', $tenant->emergency_contact_relationship);
        $this->assertSame('500.00', $tenant->security_deposit);
        $this->assertSame('100.00', $tenant->advance_payment);
        $this->assertSame($proprietor->id, $tenant->consent_recorded_by);
        $this->assertNotNull($tenant->data_privacy_consent_at);
        $this->assertSame(Tenant::STATUS_RESERVED, $tenant->status);
        $this->assertNull($tenant->move_in_date);
        $this->assertSame($bed->id, $tenant->currentAssignment->bed_id);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('sms_logs', 0);

        $this->get(route('proprietor.tenants.create'))->assertOk()->assertSee('No available beds.');
        $this->post(route('proprietor.tenants.store'), $this->form($bed, ['email' => 'second@example.com']))
            ->assertSessionHasErrors('bed_id');
        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);

        $this->get(route('proprietor.tenants.index'))->assertOk()->assertSee('Not yet moved in');
        $this->get(route('proprietor.tenants.edit', $tenant))->assertOk()->assertSee('Set when the tenant moves in.');
        $this->post(route('proprietor.payments.store'), [
            'tenant_id' => $tenant->id, 'amount' => 5000, 'due_date' => '2026-11-30', 'payment_type' => 'full', 'paid_date' => \App\Models\Tenant::localDate(), 'receipt_number' => 'OR-1',
        ])->assertSessionHasErrors('tenant_id');
        $this->post(route('proprietor.utility-bills.store'), [
            'room_id' => $bed->room_id, 'bill_type' => 'water', 'billing_period_start' => '2026-11-01',
            'billing_period_end' => '2026-11-13', 'amount' => 100, 'due_date' => '2026-11-30', 'status' => 'pending',
        ])->assertSessionHasErrors('room_id');
        $this->assertDatabaseCount('payments', 0);

        $balance = app(TenantBalanceService::class);
        $this->travelTo(now()->setDate(2026, 11, 21)->setTime(12, 0));
        $this->assertSame(Tenant::STATUS_RESERVED, $tenant->fresh()->status);
        $this->assertTrue($balance->billingEntries($tenant, '2026-11-01', '2026-11-30')->isEmpty());

        $edit = $this->form($bed, ['status' => Tenant::STATUS_ACTIVE]);
        $this->put(route('proprietor.tenants.update', $tenant), $edit)->assertSessionHasErrors('move_in_date');
        $this->put(route('proprietor.tenants.update', $tenant), $edit + ['move_in_date' => '2026-11-22'])
            ->assertSessionHasErrors('move_in_date');
        $holdId = $tenant->currentAssignment->id;
        $this->put(route('proprietor.tenants.update', $tenant), $edit + ['move_in_date' => '2026-11-21'])
            ->assertSessionHasNoErrors()->assertRedirect(route('proprietor.tenants.index'));

        $tenant->refresh();
        $this->assertSame(Tenant::STATUS_ACTIVE, $tenant->status);
        $this->assertSame('2026-11-21', $tenant->move_in_date->toDateString());
        $this->assertSame($holdId, $tenant->currentAssignment->id);
        $this->assertSame('2026-11-21', $tenant->currentAssignment->start_date->toDateString());
        $this->get(route('proprietor.tenants.edit', $tenant))->assertOk()->assertDontSee('value="reserved"', false);
        $this->put(route('proprietor.tenants.update', $tenant), $this->form($bed))->assertSessionHasErrors('status');
        $this->put(route('proprietor.tenants.update', $tenant), $edit)->assertSessionHasNoErrors();
        $this->assertSame('2026-11-21', $tenant->fresh()->move_in_date->toDateString());

        $this->travelTo(now()->setDate(2026, 12, 31)->setTime(12, 0));
        $this->assertEquals([5000.0, 5000.0], $balance->billingEntries($tenant, '2026-11-01', '2026-12-31')->pluck('amount')->all()); // due Nov 21 and Dec 21, full months
    }

    public function test_bed_cannot_be_submitted_with_a_different_room_or_type(): void
    {
        $admin = User::factory()->create(['role' => 'proprietor']);
        $cabin = Room::create(['room_number' => 'CABIN 1', 'floor' => '1', 'capacity' => 1, 'monthly_rate' => 1600]);
        $suite = Room::create(['room_number' => 'SUITE 1', 'floor' => '1', 'capacity' => 1, 'monthly_rate' => 2000]);
        $bed = Bed::create(['room_id' => $suite->id, 'bed_label' => 'A']);

        $this->actingAs($admin)->post(route('proprietor.tenants.store'), $this->form($bed, [
            'room_type' => 'CABIN', 'room_id' => $cabin->id,
        ]))->assertSessionHasErrors('bed_id');
        $this->post(route('proprietor.tenants.store'), $this->form($bed, [
            'room_type' => 'CABIN', 'room_id' => $suite->id,
        ]))->assertSessionHasErrors('bed_id');
        $this->assertDatabaseCount('tenants', 0);
    }

    public function test_editing_legacy_tenant_keeps_password_and_accepts_missing_new_profile_fields(): void
    {
        $admin = User::factory()->create(['role' => 'proprietor']);
        $user = User::factory()->create(['role' => 'tenant', 'contact_number' => 'old local number']);
        $tenant = Tenant::create(['user_id' => $user->id]);
        $this->actingAs($admin)->put(route('proprietor.tenants.update', $tenant), [
            'first_name' => 'Ana', 'last_name' => 'Cruz', 'email' => ' NEW@example.com ',
            'contact_number' => 'old local number', 'status' => 'inactive',
        ])->assertSessionHasNoErrors()->assertRedirect(route('proprietor.tenants.index'));

        $this->assertSame('new@example.com', $user->fresh()->email);
        $this->assertSame($user->password, $user->fresh()->password);
        $this->assertNull($tenant->fresh()->currentAssignment);
        $this->assertNull($tenant->fresh()->data_privacy_consent_at);
    }

    public function test_first_login_requires_a_different_password_before_accessing_the_portal(): void
    {
        $tenant = User::factory()->create([
            'role' => 'tenant', 'password' => Hash::make('TEMP09171234567'), 'must_change_password' => true,
        ]);
        Tenant::create(['user_id' => $tenant->id]);

        $this->post(route('login'), ['email' => $tenant->email, 'password' => 'TEMP09171234567'])
            ->assertRedirect(route('tenant.password.edit'));
        $this->get(route('tenant.complaints.create'))->assertRedirect(route('tenant.password.edit'));
        $this->get(route('tenant.password.edit'))->assertOk();

        $this->put(route('password.update'), [
            'current_password' => 'TEMP09171234567', 'password' => 'TEMP09171234567', 'password_confirmation' => 'TEMP09171234567',
        ])->assertSessionHasErrorsIn('updatePassword', 'password');
        $this->assertTrue($tenant->fresh()->must_change_password);
        $this->withCookie(config('session.cookie'), session()->getId())
            ->get(route('tenant.password.edit'))
            ->assertSee('Choose a new password that differs from your initial password.');

        $this->put(route('password.update'), [
            'current_password' => 'TEMP09171234567', 'password' => 'Different-long-password-1!', 'password_confirmation' => 'Different-long-password-1!',
        ])->assertRedirect(route('tenant.dashboard'));
        $this->assertFalse($tenant->fresh()->must_change_password);
        $this->get(route('tenant.complaints.create'))->assertOk();
    }

    public function test_tenant_password_errors_are_shown_beside_the_fields_to_correct(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);
        Tenant::create(['user_id' => $tenant->id]);
        $page = route('tenant.password.edit');
        $originalPassword = $tenant->password;

        $this->actingAs($tenant)->get(route('notifications.unread-count'))
            ->assertOk()
            ->assertSessionHas('_previous.url', route('notifications.unread-count'));

        $response = $this->put(route('password.update'), [
            'current_password' => 'incorrect-password',
            'password' => 'short',
            'password_confirmation' => 'different-password',
        ])->assertRedirect();

        $this->assertSame($page, $response->headers->get('Location'));
        $response->assertSessionHasErrorsIn('updatePassword', ['current_password', 'password', 'password_confirmation'])
            ->assertSessionMissing('_old_input.current_password')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');

        $this->withCookie(config('session.cookie'), session()->getId())
            ->get($page)
            ->assertOk()
            ->assertSee('aria-describedby="update_password_current_password_error"', false)
            ->assertSee('aria-describedby="update_password_password_error tenant-password-requirements"', false)
            ->assertSee('aria-describedby="update_password_password_confirmation_error"', false)
            ->assertSee('Password wasn’t changed.')
            ->assertSee('Your current password is incorrect.')
            ->assertSee('Use at least 8 characters.')
            ->assertSee('Include an uppercase letter (A–Z) and a lowercase letter (a–z).')
            ->assertSee('Include a number (0–9).')
            ->assertSee('Include a special character such as !, @, #, $, or %.')
            ->assertSee('The passwords do not match.');

        $this->assertSame($originalPassword, $tenant->fresh()->password);
    }

    public function test_reused_email_saves_a_tenant_without_touching_the_existing_login(): void
    {
        $admin = User::factory()->create(['role' => 'proprietor', 'email' => 'shared@example.com']);
        $originalPassword = $admin->password;

        $this->actingAs($admin)->post(route('proprietor.tenants.store'), $this->form($this->bed(), [
            'email' => ' SHARED@example.com ',
        ]))->assertRedirect(route('proprietor.tenants.index'))->assertSessionHas('notice',
            'Tenant saved. A login with this email already exists, so no new account was created.'
        );

        $tenant = Tenant::where('email', 'shared@example.com')->sole();
        $this->assertNull($tenant->user);
        $this->assertSame('Ana Santos De la Peña-Cruz', $tenant->name);
        $this->assertSame($originalPassword, $admin->fresh()->password);
        $this->assertSame('proprietor', $admin->fresh()->role);
        $this->assertDatabaseCount('users', 1);

        $this->get(route('proprietor.tenants.index', ['search' => 'shared@example.com']))
            ->assertOk()->assertSee('No login account');
        $this->get(route('proprietor.tenants.edit', $tenant))->assertOk()->assertSee('shared@example.com');

        $another = Tenant::factory()->create(['email' => ' SHARED@example.com ']);
        $this->assertSame('shared@example.com', $another->email);
        $this->assertSame(2, Tenant::where('email', 'shared@example.com')->count());

        $this->delete(route('proprietor.tenants.destroy', $tenant))->assertRedirect();
        $this->assertModelMissing($tenant);
        $this->assertModelExists($admin);
    }

    public function test_edit_saves_a_reused_contact_email_and_keeps_both_login_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'proprietor', 'email' => 'shared@example.com']);
        $user = User::factory()->create(['role' => 'tenant', 'email' => 'original@example.com']);
        $tenant = Tenant::create(['user_id' => $user->id, 'email' => $user->email, 'move_in_date' => '2026-01-01']);
        $profile = ['first_name' => 'Ana', 'last_name' => 'Cruz', 'status' => 'active'];

        $this->actingAs($admin)->put(route('proprietor.tenants.update', $tenant), [
            ...$profile, 'email' => ' SHARED@example.com ',
        ])->assertRedirect(route('proprietor.tenants.index'))->assertSessionHas('notice');

        $this->assertSame('shared@example.com', $tenant->fresh()->email);
        $this->assertSame('original@example.com', $user->fresh()->email);
        $this->assertSame('shared@example.com', $admin->fresh()->email);
        $this->assertSame($user->password, $user->fresh()->password);

        $this->put(route('proprietor.tenants.update', $tenant), [
            ...$profile, 'email' => ' UNUSED@example.com ',
        ])->assertRedirect(route('proprietor.tenants.index'))->assertSessionHas('success');
        $this->assertSame('unused@example.com', $user->fresh()->email);
        $this->assertSame('unused@example.com', $tenant->fresh()->email);
        $this->assertSame($user->password, $user->fresh()->password);
    }

    public function test_tenant_cannot_access_proprietor_tenant_list(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);

        $this->actingAs($tenant)
            ->get(route('proprietor.tenants.index'))
            ->assertForbidden();
    }
}
