<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The account profile page belongs to the proprietor; tenants manage their details elsewhere. */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function proprietor(): User
    {
        return User::factory()->create(['role' => 'proprietor']);
    }

    public function test_profile_page_is_displayed_without_a_delete_account_option(): void
    {
        $this->actingAs($this->proprietor())->get('/profile')->assertOk()
            ->assertSee('Profile Information')->assertSee('Update Password')
            ->assertDontSee('Delete Account');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = $this->proprietor();

        $this->actingAs($user)->patch('/profile', ['name' => 'Test User', 'email' => 'test@example.com'])
            ->assertSessionHasNoErrors()->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = $this->proprietor();

        $this->actingAs($user)->patch('/profile', ['name' => 'Test User', 'email' => $user->email])
            ->assertSessionHasNoErrors()->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_the_proprietor_account_cannot_be_deleted(): void
    {
        $user = $this->proprietor();

        // The only administrator: deleting it would lock everyone out, so there is no way to do it.
        $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertMethodNotAllowed();
        $this->assertNotNull($user->fresh());
        $this->assertAuthenticatedAs($user);
    }

    public function test_tenants_cannot_open_the_proprietor_profile_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'tenant']))->get('/profile')->assertForbidden();
    }
}
