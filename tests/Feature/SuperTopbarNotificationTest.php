<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SuperAdmin;
use App\Models\SuperNotificationDismissal;
use App\Models\User;
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

        $notificationData = app(SuperTopbarNotificationService::class)->build($superAdmin->id);
        $notifications = $notificationData['notifications'];

        $this->assertSame(1, $notificationData['unreadCount']);
        $this->assertSame(2, $notifications->count());

        $pendingNotification = $notifications->firstWhere('is_urgent', true);
        $activityNotification = $notifications->firstWhere('is_urgent', false);

        $this->assertSame('Pengelola barang menunggu verifikasi', $pendingNotification['title']);
        $this->assertStringContainsString($pendingAdmin->nama, $pendingNotification['message']);
        $this->assertSame('Perlu tindakan', $pendingNotification['tag']);
        $this->assertStringContainsString('admin-verifications', $pendingNotification['action_url']);

        $this->assertSame('Status pengelola barang Aktif', $activityNotification['title']);
        $this->assertStringContainsString($activeAdmin->nama, $activityNotification['message']);
        $this->assertSame('Aktivitas', $activityNotification['tag']);
        $this->assertStringContainsString('super/pengelola', $activityNotification['action_url']);
    }

    public function test_super_topbar_notifications_are_scoped_to_authenticated_super_admin(): void
    {
        $superAdminA = $this->createSuperAdmin('super-a@example.com', 'super-a');
        $superAdminB = $this->createSuperAdmin('super-b@example.com', 'super-b');

        $pendingA = $this->createAdmin($superAdminA, 'Pending Super A', 'pending', now()->subMinute());
        $activeA = $this->createAdmin($superAdminA, 'Active Super A', 'active', now()->subMinutes(3));
        $pendingB = $this->createAdmin($superAdminB, 'Pending Super B', 'pending', now()->subMinutes(2));
        $activeB = $this->createAdmin($superAdminB, 'Active Super B', 'active', now()->subMinutes(4));

        $this->actingAs($superAdminA, 'super_admin');
        $htmlForA = view('super.partials.topbar')->render();

        $this->assertStringContainsString($pendingA->nama, $htmlForA);
        $this->assertStringContainsString($activeA->nama, $htmlForA);
        $this->assertStringNotContainsString($pendingB->nama, $htmlForA);
        $this->assertStringNotContainsString($activeB->nama, $htmlForA);
        $this->assertStringContainsString('Butuh tindakan <strong>1</strong>', $htmlForA);

        $this->actingAs($superAdminB, 'super_admin');
        $htmlForB = view('super.partials.topbar')->render();

        $this->assertStringContainsString($pendingB->nama, $htmlForB);
        $this->assertStringContainsString($activeB->nama, $htmlForB);
        $this->assertStringNotContainsString($pendingA->nama, $htmlForB);
        $this->assertStringNotContainsString($activeA->nama, $htmlForB);
        $this->assertStringContainsString('Butuh tindakan <strong>1</strong>', $htmlForB);
    }

    public function test_super_topbar_notifications_are_empty_without_super_admin_guard(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $pendingAdmin = $this->createAdmin($superAdmin, 'Pending Guarded Super', 'pending', now()->subMinute());
        $activeAdmin = $this->createAdmin($superAdmin, 'Active Guarded Super', 'active', now()->subMinutes(2));

        $guestHtml = view('super.partials.topbar')->render();
        $this->assertStringNotContainsString($pendingAdmin->nama, $guestHtml);
        $this->assertStringNotContainsString($activeAdmin->nama, $guestHtml);
        $this->assertStringContainsString('Butuh tindakan <strong>0</strong>', $guestHtml);

        $this->actingAs(User::factory()->create());
        $userHtml = view('super.partials.topbar')->render();
        $this->assertStringNotContainsString($pendingAdmin->nama, $userHtml);
        $this->assertStringNotContainsString($activeAdmin->nama, $userHtml);
        $this->assertStringContainsString('Butuh tindakan <strong>0</strong>', $userHtml);

        $admin = $this->createAdmin($superAdmin, 'Regular Admin Guard', 'active', now()->subMinutes(3));
        $this->actingAs($admin, 'admin');
        $adminHtml = view('super.partials.topbar')->render();
        $this->assertStringNotContainsString($pendingAdmin->nama, $adminHtml);
        $this->assertStringNotContainsString($activeAdmin->nama, $adminHtml);
        $this->assertStringContainsString('Butuh tindakan <strong>0</strong>', $adminHtml);

        $serviceData = app(SuperTopbarNotificationService::class)->build();
        $this->assertSame(0, $serviceData['unreadCount']);
        $this->assertCount(0, $serviceData['notifications']);
    }

    public function test_super_admin_can_dismiss_single_activity_from_topbar_without_deleting_admin(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $activeAdmin = $this->createAdmin($superAdmin, 'Active Dismissed Super', 'active', now()->subMinutes(2));

        $notificationData = app(SuperTopbarNotificationService::class)->build($superAdmin->id);
        $activityNotification = $notificationData['notifications']->firstWhere('is_dismissible', true);

        $this->assertNotNull($activityNotification);
        $this->assertStringStartsWith('admin_status:'.$activeAdmin->id.':active:', $activityNotification['item_key']);

        $this->actingAs($superAdmin, 'super_admin')
            ->from(route('super.dashboard'))
            ->post(route('super.notifications.dismiss'), [
                'item_key' => $activityNotification['item_key'],
            ])
            ->assertRedirect(route('super.dashboard'))
            ->assertSessionHas('status', 'Riwayat notifikasi disembunyikan.');

        $this->assertDatabaseHas('super_notification_dismissals', [
            'super_admin_id' => $superAdmin->id,
            'item_key' => $activityNotification['item_key'],
        ]);

        $updatedNotifications = app(SuperTopbarNotificationService::class)->build($superAdmin->id)['notifications'];

        $this->assertFalse($updatedNotifications->contains(fn (array $notification) => str_contains($notification['message'], $activeAdmin->nama)));
        $this->assertDatabaseHas('admins', [
            'id' => $activeAdmin->id,
            'status_verifikasi' => 'active',
        ]);
    }

    public function test_super_notification_dismissal_is_scoped_per_super_admin(): void
    {
        $superAdminA = $this->createSuperAdmin('dismiss-a@example.com', 'dismiss-a');
        $superAdminB = $this->createSuperAdmin('dismiss-b@example.com', 'dismiss-b');
        $activeA = $this->createAdmin($superAdminA, 'Activity Super A Dismissed', 'active', now()->subMinutes(3));
        $activeB = $this->createAdmin($superAdminB, 'Activity Super B Visible', 'active', now()->subMinutes(4));

        $activityA = app(SuperTopbarNotificationService::class)
            ->build($superAdminA->id)['notifications']
            ->firstWhere('is_dismissible', true);

        $this->actingAs($superAdminA, 'super_admin')
            ->post(route('super.notifications.dismiss'), [
                'item_key' => $activityA['item_key'],
            ])
            ->assertRedirect();

        $notificationsA = app(SuperTopbarNotificationService::class)->build($superAdminA->id)['notifications'];
        $notificationsB = app(SuperTopbarNotificationService::class)->build($superAdminB->id)['notifications'];

        $this->assertFalse($notificationsA->contains(fn (array $notification) => str_contains($notification['message'], $activeA->nama)));
        $this->assertTrue($notificationsB->contains(fn (array $notification) => str_contains($notification['message'], $activeB->nama)));
    }

    public function test_super_admin_can_clear_activity_history_without_hiding_pending_verifications(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $pendingAdmin = $this->createAdmin($superAdmin, 'Pending Tetap Tampil', 'pending', now()->subMinute());
        $activeAdmin = $this->createAdmin($superAdmin, 'Active Dibersihkan', 'active', now()->subMinutes(2));
        $rejectedAdmin = $this->createAdmin($superAdmin, 'Rejected Dibersihkan', 'rejected', now()->subMinutes(3));

        $this->actingAs($superAdmin, 'super_admin')
            ->from(route('super.dashboard'))
            ->post(route('super.notifications.dismiss-activities'))
            ->assertRedirect(route('super.dashboard'))
            ->assertSessionHas('status', 'Riwayat notifikasi berhasil dibersihkan dari topbar.');

        $notificationData = app(SuperTopbarNotificationService::class)->build($superAdmin->id);
        $notifications = $notificationData['notifications'];

        $this->assertSame(1, $notificationData['unreadCount']);
        $this->assertTrue($notifications->contains(fn (array $notification) => str_contains($notification['message'], $pendingAdmin->nama)));
        $this->assertFalse($notifications->contains(fn (array $notification) => str_contains($notification['message'], $activeAdmin->nama)));
        $this->assertFalse($notifications->contains(fn (array $notification) => str_contains($notification['message'], $rejectedAdmin->nama)));
        $this->assertSame(2, SuperNotificationDismissal::query()->where('super_admin_id', $superAdmin->id)->count());
        $this->assertDatabaseCount('admins', 3);
    }

    public function test_pending_verification_cannot_be_hidden_by_dismiss_feature(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $pendingAdmin = $this->createAdmin($superAdmin, 'Pending Tidak Bisa Disembunyikan', 'pending', now()->subMinute());

        $pendingNotification = app(SuperTopbarNotificationService::class)
            ->build($superAdmin->id)['notifications']
            ->firstWhere('is_urgent', true);

        $this->assertNotNull($pendingNotification);
        $this->assertFalse($pendingNotification['is_dismissible']);

        $this->actingAs($superAdmin, 'super_admin')
            ->post(route('super.notifications.dismiss'), [
                'item_key' => $pendingNotification['item_key'],
            ])
            ->assertRedirect();

        $notificationData = app(SuperTopbarNotificationService::class)->build($superAdmin->id);

        $this->assertSame(1, $notificationData['unreadCount']);
        $this->assertTrue($notificationData['notifications']->contains(fn (array $notification) => str_contains($notification['message'], $pendingAdmin->nama)));

        $this->actingAs($superAdmin, 'super_admin');
        $html = view('super.partials.topbar')->render();

        $this->assertStringContainsString($pendingAdmin->nama, $html);
        $this->assertStringNotContainsString('Bersihkan Riwayat', $html);
    }

    public function test_guest_user_and_admin_cannot_access_super_notification_dismiss_routes(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $user = User::factory()->create();
        $admin = $this->createAdmin($superAdmin, 'Admin Route Guard', 'active', now()->subMinutes(2));

        $this->post(route('super.notifications.dismiss'), [
            'item_key' => 'admin_status:1:active:1',
        ])->assertRedirect(route('super.login'));

        $this->post(route('super.notifications.dismiss-activities'))
            ->assertRedirect(route('super.login'));

        $this->actingAs($user)
            ->post(route('super.notifications.dismiss'), [
                'item_key' => 'admin_status:1:active:1',
            ])
            ->assertRedirect(route('super.login'));

        $this->actingAs($user)
            ->post(route('super.notifications.dismiss-activities'))
            ->assertRedirect(route('super.login'));

        $this->actingAs($admin, 'admin')
            ->post(route('super.notifications.dismiss'), [
                'item_key' => 'admin_status:1:active:1',
            ])
            ->assertRedirect(route('super.login'));

        $this->actingAs($admin, 'admin')
            ->post(route('super.notifications.dismiss-activities'))
            ->assertRedirect(route('super.login'));
    }

    public function test_super_admin_can_view_dismissed_notifications_page(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $dismissal = SuperNotificationDismissal::query()->create([
            'super_admin_id' => $superAdmin->id,
            'item_key' => 'admin_status:1:active:100',
            'dismissed_at' => now(),
        ]);

        $this->actingAs($superAdmin, 'super_admin')
            ->get(route('super.notifications.dismissed'))
            ->assertOk()
            ->assertSee('Riwayat Notifikasi Disembunyikan')
            ->assertSee($dismissal->item_key)
            ->assertSee('Tampilkan Lagi')
            ->assertSee('Tampilkan Semua');
    }

    public function test_guest_user_and_admin_cannot_access_dismissed_notifications_page(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $dismissal = SuperNotificationDismissal::query()->create([
            'super_admin_id' => $superAdmin->id,
            'item_key' => 'admin_status:1:active:100',
            'dismissed_at' => now(),
        ]);
        $user = User::factory()->create();
        $admin = $this->createAdmin($superAdmin, 'Admin Dismissed Page Guard', 'active', now()->subMinutes(2));

        $this->get(route('super.notifications.dismissed'))
            ->assertRedirect(route('super.login'));
        $this->delete(route('super.notifications.dismissed.destroy', $dismissal))
            ->assertRedirect(route('super.login'));
        $this->delete(route('super.notifications.dismissed.clear'))
            ->assertRedirect(route('super.login'));

        $this->actingAs($user)
            ->get(route('super.notifications.dismissed'))
            ->assertRedirect(route('super.login'));
        $this->actingAs($user)
            ->delete(route('super.notifications.dismissed.destroy', $dismissal))
            ->assertRedirect(route('super.login'));
        $this->actingAs($user)
            ->delete(route('super.notifications.dismissed.clear'))
            ->assertRedirect(route('super.login'));

        $this->actingAs($admin, 'admin')
            ->get(route('super.notifications.dismissed'))
            ->assertRedirect(route('super.login'));
        $this->actingAs($admin, 'admin')
            ->delete(route('super.notifications.dismissed.destroy', $dismissal))
            ->assertRedirect(route('super.login'));
        $this->actingAs($admin, 'admin')
            ->delete(route('super.notifications.dismissed.clear'))
            ->assertRedirect(route('super.login'));
    }

    public function test_super_admin_only_sees_own_dismissed_notifications(): void
    {
        $superAdminA = $this->createSuperAdmin('hidden-a@example.com', 'hidden-a');
        $superAdminB = $this->createSuperAdmin('hidden-b@example.com', 'hidden-b');
        $dismissalA = SuperNotificationDismissal::query()->create([
            'super_admin_id' => $superAdminA->id,
            'item_key' => 'admin_status:10:active:100',
            'dismissed_at' => now(),
        ]);
        $dismissalB = SuperNotificationDismissal::query()->create([
            'super_admin_id' => $superAdminB->id,
            'item_key' => 'admin_status:20:rejected:200',
            'dismissed_at' => now(),
        ]);

        $this->actingAs($superAdminA, 'super_admin')
            ->get(route('super.notifications.dismissed'))
            ->assertOk()
            ->assertSee($dismissalA->item_key)
            ->assertDontSee($dismissalB->item_key);
    }

    public function test_super_admin_can_restore_single_dismissed_activity(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $activeAdmin = $this->createAdmin($superAdmin, 'Activity Dipulihkan', 'active', now()->subMinutes(2));
        $activity = app(SuperTopbarNotificationService::class)
            ->build($superAdmin->id)['notifications']
            ->firstWhere('is_dismissible', true);

        $dismissal = SuperNotificationDismissal::query()->create([
            'super_admin_id' => $superAdmin->id,
            'item_key' => $activity['item_key'],
            'dismissed_at' => now(),
        ]);

        $this->assertFalse(app(SuperTopbarNotificationService::class)
            ->build($superAdmin->id)['notifications']
            ->contains(fn (array $notification) => str_contains($notification['message'], $activeAdmin->nama)));

        $this->actingAs($superAdmin, 'super_admin')
            ->from(route('super.notifications.dismissed'))
            ->delete(route('super.notifications.dismissed.destroy', $dismissal))
            ->assertRedirect(route('super.notifications.dismissed'))
            ->assertSessionHas('status', 'Riwayat notifikasi berhasil ditampilkan kembali.');

        $this->assertDatabaseMissing('super_notification_dismissals', [
            'id' => $dismissal->id,
        ]);
        $this->assertTrue(app(SuperTopbarNotificationService::class)
            ->build($superAdmin->id)['notifications']
            ->contains(fn (array $notification) => str_contains($notification['message'], $activeAdmin->nama)));
        $this->assertDatabaseHas('admins', [
            'id' => $activeAdmin->id,
            'status_verifikasi' => 'active',
        ]);
    }

    public function test_super_admin_cannot_restore_dismissal_owned_by_another_super_admin(): void
    {
        $superAdminA = $this->createSuperAdmin('restore-a@example.com', 'restore-a');
        $superAdminB = $this->createSuperAdmin('restore-b@example.com', 'restore-b');
        $foreignDismissal = SuperNotificationDismissal::query()->create([
            'super_admin_id' => $superAdminB->id,
            'item_key' => 'admin_status:99:active:999',
            'dismissed_at' => now(),
        ]);

        $this->actingAs($superAdminA, 'super_admin')
            ->delete(route('super.notifications.dismissed.destroy', $foreignDismissal))
            ->assertNotFound();

        $this->assertDatabaseHas('super_notification_dismissals', [
            'id' => $foreignDismissal->id,
            'super_admin_id' => $superAdminB->id,
        ]);
    }

    public function test_restore_all_only_restores_current_super_admin_dismissals_and_pending_stays_visible(): void
    {
        $superAdminA = $this->createSuperAdmin('clear-a@example.com', 'clear-a');
        $superAdminB = $this->createSuperAdmin('clear-b@example.com', 'clear-b');
        $pendingAdmin = $this->createAdmin($superAdminA, 'Pending Tetap Setelah Pulih', 'pending', now()->subMinute());
        $activeAdmin = $this->createAdmin($superAdminA, 'Activity Pulih Semua', 'active', now()->subMinutes(2));

        $activity = app(SuperTopbarNotificationService::class)
            ->build($superAdminA->id)['notifications']
            ->firstWhere('is_dismissible', true);

        SuperNotificationDismissal::query()->create([
            'super_admin_id' => $superAdminA->id,
            'item_key' => $activity['item_key'],
            'dismissed_at' => now(),
        ]);
        $foreignDismissal = SuperNotificationDismissal::query()->create([
            'super_admin_id' => $superAdminB->id,
            'item_key' => 'admin_status:77:inactive:777',
            'dismissed_at' => now(),
        ]);

        $this->actingAs($superAdminA, 'super_admin')
            ->from(route('super.notifications.dismissed'))
            ->delete(route('super.notifications.dismissed.clear'))
            ->assertRedirect(route('super.notifications.dismissed'))
            ->assertSessionHas('status', 'Semua riwayat notifikasi berhasil ditampilkan kembali.');

        $this->assertSame(0, SuperNotificationDismissal::query()->where('super_admin_id', $superAdminA->id)->count());
        $this->assertDatabaseHas('super_notification_dismissals', [
            'id' => $foreignDismissal->id,
            'super_admin_id' => $superAdminB->id,
        ]);

        $notificationData = app(SuperTopbarNotificationService::class)->build($superAdminA->id);
        $this->assertSame(1, $notificationData['unreadCount']);
        $this->assertTrue($notificationData['notifications']->contains(fn (array $notification) => str_contains($notification['message'], $pendingAdmin->nama)));
        $this->assertTrue($notificationData['notifications']->contains(fn (array $notification) => str_contains($notification['message'], $activeAdmin->nama)));
        $this->assertDatabaseHas('admins', [
            'id' => $activeAdmin->id,
            'status_verifikasi' => 'active',
        ]);
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
            'email' => str($name)->slug('-').'@example.com',
            'username' => (string) str($name)->slug('-'),
            'password' => Hash::make('password123'),
            'instansi' => 'Instansi '.$name,
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Topbar No. 1',
            'status_verifikasi' => $status,
            'verified_at' => $status === 'pending' ? null : now()->subMinutes(2),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
