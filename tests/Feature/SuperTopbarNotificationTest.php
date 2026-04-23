<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SuperAdmin;
use App\Services\Super\Notifications\SuperTopbarNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperTopbarNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_topbar_notifications_prioritize_pending_and_include_activity_items(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $pendingAdmin = $this->createAdmin($superAdmin, 'Admin Pending Notifikasi', 'pending', now()->subMinute());
        $activeAdmin = $this->createAdmin($superAdmin, 'Admin Aktif Notifikasi', 'active', now()->subMinutes(5));

        $notificationData = app(SuperTopbarNotificationService::class)->build();
        $notifications = $notificationData['notifications'];

        $this->assertSame(1, $notificationData['unreadCount']);
        $this->assertSame(2, $notifications->count());

        $pendingNotification = $notifications->firstWhere('is_urgent', true);
        $activityNotification = $notifications->firstWhere('is_urgent', false);

        $this->assertSame('Admin menunggu verifikasi', $pendingNotification['title']);
        $this->assertStringContainsString($pendingAdmin->nama, $pendingNotification['message']);
        $this->assertSame('Perlu tindakan', $pendingNotification['tag']);
        $this->assertStringContainsString('admin-verifications', $pendingNotification['action_url']);

        $this->assertSame('Status admin Aktif', $activityNotification['title']);
        $this->assertStringContainsString($activeAdmin->nama, $activityNotification['message']);
        $this->assertSame('Aktivitas', $activityNotification['tag']);
        $this->assertStringContainsString('super/admins', $activityNotification['action_url']);
    }

    private function createSuperAdmin(
        string $email = 'topbar-super@example.com',
        string $username = 'topbar-super'
    ): SuperAdmin {
        return SuperAdmin::query()->create([
            'nama' => 'Super Topbar',
            'email' => $email,
            'username' => $username,
            'password' => Hash::make('password123'),
        ]);
    }

    private function createAdmin(SuperAdmin $superAdmin, string $name, string $status, mixed $createdAt): Admin
    {
        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'nama' => $name,
            'email' => str($name)->slug('-') . '@example.com',
            'username' => (string) str($name)->slug('-'),
            'password' => Hash::make('password123'),
            'instansi' => 'Instansi ' . $name,
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Topbar No. 1',
            'status_verifikasi' => $status,
            'verified_at' => $status === 'pending' ? null : now()->subMinutes(2),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
