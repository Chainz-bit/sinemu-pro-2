<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserNotificationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_mark_own_notification_as_read(): void
    {
        $user = $this->createUser('owner-user');
        $notification = $this->createNotification($user);

        $this->from(route('user.dashboard'))
            ->actingAs($user)
            ->post(route('user.notifications.read', $notification))
            ->assertRedirect(route('user.dashboard'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_read_notification_owned_by_another_user(): void
    {
        $userA = $this->createUser('reader-user-a');
        $userB = $this->createUser('reader-user-b');
        $foreignNotification = $this->createNotification($userB);

        $this->actingAs($userA)
            ->post(route('user.notifications.read', $foreignNotification))
            ->assertForbidden();

        $this->assertNull($foreignNotification->fresh()->read_at);
    }

    public function test_user_cannot_mark_unread_notification_owned_by_another_user(): void
    {
        $userA = $this->createUser('unreader-user-a');
        $userB = $this->createUser('unreader-user-b');
        $foreignNotification = $this->createNotification($userB, now()->subHour());

        $this->actingAs($userA)
            ->post(route('user.notifications.unread', $foreignNotification))
            ->assertForbidden();

        $this->assertNotNull($foreignNotification->fresh()->read_at);
    }

    public function test_user_cannot_delete_notification_owned_by_another_user(): void
    {
        $userA = $this->createUser('deleter-user-a');
        $userB = $this->createUser('deleter-user-b');
        $foreignNotification = $this->createNotification($userB);

        $this->actingAs($userA)
            ->delete(route('user.notifications.destroy', $foreignNotification))
            ->assertForbidden();

        $this->assertDatabaseHas('user_notifications', [
            'id' => $foreignNotification->id,
            'user_id' => $userB->id,
        ]);
    }

    public function test_user_read_all_only_marks_current_user_notifications(): void
    {
        $userA = $this->createUser('read-all-user-a');
        $userB = $this->createUser('read-all-user-b');
        $ownNotification = $this->createNotification($userA);
        $foreignNotification = $this->createNotification($userB);

        $this->actingAs($userA)
            ->post(route('user.notifications.read-all'))
            ->assertRedirect();

        $this->assertNotNull($ownNotification->fresh()->read_at);
        $this->assertNull($foreignNotification->fresh()->read_at);
    }

    public function test_user_delete_all_only_deletes_current_user_notifications(): void
    {
        $userA = $this->createUser('delete-all-user-a');
        $userB = $this->createUser('delete-all-user-b');
        $ownNotification = $this->createNotification($userA);
        $foreignNotification = $this->createNotification($userB);

        $this->actingAs($userA)
            ->delete(route('user.notifications.destroy-all'))
            ->assertRedirect();

        $this->assertDatabaseMissing('user_notifications', [
            'id' => $ownNotification->id,
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'id' => $foreignNotification->id,
            'user_id' => $userB->id,
        ]);
    }

    public function test_guest_and_admin_cannot_access_user_notification_routes(): void
    {
        $user = $this->createUser('guarded-user');
        $notification = $this->createNotification($user);

        $this->post(route('user.notifications.read-all'))
            ->assertRedirect(route('login'));

        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')
            ->post(route('user.notifications.read-all'))
            ->assertForbidden();

        $this->actingAs($admin, 'admin')
            ->post(route('user.notifications.read', $notification))
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    private function createUser(string $username): User
    {
        $user = User::query()->create([
            'name' => 'User '.$username,
            'nama' => 'User '.$username,
            'username' => $username,
            'email' => $username.'@example.com',
            'nomor_telepon' => '0812'.random_int(10000000, 99999999),
            'password' => 'password123',
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function createAdmin(): Admin
    {
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super User Notification',
            'email' => 'user-notification-super@example.com',
            'username' => 'user-notification-super',
            'password' => Hash::make('password123'),
        ]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'nama' => 'Admin User Notification',
            'email' => 'user-notification-admin@example.com',
            'username' => 'user-notification-admin',
            'password' => Hash::make('password123'),
            'instansi' => 'Instansi User Notification',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. User Notification No. 1',
            'status_verifikasi' => Admin::STATUS_ACTIVE,
            'verified_at' => now(),
        ]);
    }

    private function createNotification(User $user, mixed $readAt = null): UserNotification
    {
        return UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'security_test',
            'title' => 'Notifikasi Security',
            'message' => 'Notifikasi untuk menguji scoping user.',
            'read_at' => $readAt,
        ]);
    }
}
