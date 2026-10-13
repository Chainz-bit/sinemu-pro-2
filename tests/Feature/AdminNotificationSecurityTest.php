<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminNotificationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_mark_own_notification_as_read(): void
    {
        $admin = $this->createAdmin('owner-admin');
        $notification = $this->createNotification($admin);

        $this->from(route('admin.dashboard'))
            ->actingAs($admin, 'admin')
            ->post(route('admin.notifications.read', $notification))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_admin_cannot_mark_read_notification_owned_by_another_admin(): void
    {
        $adminA = $this->createAdmin('reader-admin-a');
        $adminB = $this->createAdmin('reader-admin-b');
        $foreignNotification = $this->createNotification($adminB);

        $this->actingAs($adminA, 'admin')
            ->post(route('admin.notifications.read', $foreignNotification))
            ->assertForbidden();

        $this->assertNull($foreignNotification->fresh()->read_at);
    }

    public function test_admin_cannot_delete_notification_owned_by_another_admin(): void
    {
        $adminA = $this->createAdmin('deleter-admin-a');
        $adminB = $this->createAdmin('deleter-admin-b');
        $foreignNotification = $this->createNotification($adminB);

        $this->actingAs($adminA, 'admin')
            ->delete(route('admin.notifications.destroy', $foreignNotification))
            ->assertForbidden();

        $this->assertDatabaseHas('admin_notifications', [
            'id' => $foreignNotification->id,
            'admin_id' => $adminB->id,
        ]);
    }

    public function test_admin_read_all_only_marks_current_admin_notifications(): void
    {
        $adminA = $this->createAdmin('read-all-admin-a');
        $adminB = $this->createAdmin('read-all-admin-b');
        $ownNotification = $this->createNotification($adminA);
        $foreignNotification = $this->createNotification($adminB);

        $this->actingAs($adminA, 'admin')
            ->post(route('admin.notifications.read-all'))
            ->assertRedirect();

        $this->assertNotNull($ownNotification->fresh()->read_at);
        $this->assertNull($foreignNotification->fresh()->read_at);
    }

    public function test_admin_delete_all_only_deletes_current_admin_notifications(): void
    {
        $adminA = $this->createAdmin('delete-all-admin-a');
        $adminB = $this->createAdmin('delete-all-admin-b');
        $ownNotification = $this->createNotification($adminA);
        $foreignNotification = $this->createNotification($adminB);

        $this->actingAs($adminA, 'admin')
            ->delete(route('admin.notifications.destroy-all'))
            ->assertRedirect();

        $this->assertDatabaseMissing('admin_notifications', [
            'id' => $ownNotification->id,
        ]);
        $this->assertDatabaseHas('admin_notifications', [
            'id' => $foreignNotification->id,
            'admin_id' => $adminB->id,
        ]);
    }

    public function test_non_active_admins_cannot_access_admin_notification_routes(): void
    {
        foreach ([Admin::STATUS_PENDING, Admin::STATUS_REJECTED, Admin::STATUS_INACTIVE] as $status) {
            $admin = $this->createAdmin('blocked-admin-'.$status, $status);
            $notification = $this->createNotification($admin);

            $this->actingAs($admin, 'admin')
                ->post(route('admin.notifications.read-all'))
                ->assertRedirect(route('admin.login'));

            $this->assertNull($notification->fresh()->read_at);
        }
    }

    public function test_guest_and_user_cannot_access_admin_notification_routes(): void
    {
        $admin = $this->createAdmin('admin-notification-owner');
        $notification = $this->createNotification($admin);

        $this->post(route('admin.notifications.read-all'))
            ->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->create())
            ->post(route('admin.notifications.read', $notification))
            ->assertRedirect(route('admin.login'));

        $this->assertNull($notification->fresh()->read_at);
    }

    private function createAdmin(string $username, string $status = Admin::STATUS_ACTIVE): Admin
    {
        $superAdmin = SuperAdmin::query()->firstOrCreate(
            ['email' => 'notification-security-super@example.com'],
            [
                'nama' => 'Super Notification Security',
                'username' => 'notification-security-super',
                'password' => Hash::make('password123'),
            ]
        );

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'nama' => 'Admin '.$username,
            'email' => $username.'@example.com',
            'username' => $username,
            'password' => Hash::make('password123'),
            'instansi' => 'Instansi Notifikasi',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Notifikasi No. 1',
            'status_verifikasi' => $status,
            'verified_at' => $status === Admin::STATUS_ACTIVE ? now() : null,
        ]);
    }

    private function createNotification(Admin $admin): AdminNotification
    {
        return AdminNotification::query()->create([
            'admin_id' => $admin->id,
            'type' => 'security_test',
            'title' => 'Notifikasi Security',
            'message' => 'Notifikasi untuk menguji scoping admin.',
        ]);
    }
}
