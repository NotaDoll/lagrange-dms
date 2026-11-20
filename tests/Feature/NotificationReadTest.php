<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationReadTest extends TestCase
{
    use RefreshDatabase;

    private function tenantUser(): User
    {
        $user = User::factory()->create(['role' => 'tenant']);
        Tenant::create(['user_id' => $user->id, 'status' => 'active', 'move_in_date' => '2026-08-01']);

        return $user;
    }

    public function test_a_notification_is_marked_read_only_when_it_is_opened(): void
    {
        $user = $this->tenantUser();
        $first = $user->notifications()->create(['type' => 'rent_reminder', 'message' => 'Your rent of ₱1,000.00 is due today.', 'is_read' => false]);
        $second = $user->notifications()->create(['type' => 'announcement', 'message' => 'Water interruption tomorrow.', 'is_read' => false]);

        // Viewing the list no longer marks everything read; each item carries what the modal shows.
        $this->actingAs($user)->get(route('notifications.index'))->assertOk()
            ->assertSee('data-notif-message="Your rent of ₱1,000.00 is due today."', false)
            ->assertSee('id="notifModal"', false);
        $this->assertSame(2, $user->notifications()->where('is_read', false)->count());

        $this->postJson(route('notifications.read', $first))->assertOk()->assertJson(['read' => true, 'unread' => 1]);
        $this->assertTrue($first->fresh()->is_read);
        $this->assertFalse($second->fresh()->is_read);
    }

    public function test_a_tenant_cannot_mark_someone_elses_notification(): void
    {
        $owner = $this->tenantUser();
        $notification = $owner->notifications()->create(['type' => 'announcement', 'message' => 'Hi', 'is_read' => false]);

        $this->actingAs($this->tenantUser())->postJson(route('notifications.read', $notification))->assertNotFound();
        $this->assertFalse($notification->fresh()->is_read);
    }
}
