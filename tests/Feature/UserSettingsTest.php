<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_open_settings_page(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)
            ->get(route('user.settings'));

        $response->assertOk();
        $response->assertSee('Pengaturan Akun');
        $response->assertSee('Identitas Akun');
    }

    public function test_user_can_update_settings(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)
            ->put(route('user.settings.update'), [
                'nama' => 'User Baru',
                'username' => 'user-baru',
                'email' => 'user-baru@example.com',
                'nomor_telepon' => '081222333444',
            ]);

        $response->assertRedirect(route('user.settings'));
        $response->assertSessionHas('status', 'Pengaturan akun berhasil diperbarui.');

        $user->refresh();

        $this->assertSame('User Baru', $user->nama);
        $this->assertSame('user-baru', $user->username);
        $this->assertSame('user-baru@example.com', $user->email);
        $this->assertSame('081222333444', $user->nomor_telepon);
    }

    public function test_user_settings_update_validates_required_fields(): void
    {
        $user = $this->createUser();

        $response = $this->from(route('user.settings'))
            ->actingAs($user)
            ->put(route('user.settings.update'), [
                'email' => 'bukan-email',
            ]);

        $response->assertRedirect(route('user.settings'));
        $response->assertSessionHasErrors([
            'nama',
            'username',
            'email',
        ]);
    }

    public function test_user_can_open_history_page_and_filter_notifications(): void
    {
        $user = $this->createUser();

        UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'klaim_disetujui',
            'title' => 'Klaim Disetujui',
            'message' => 'Klaim Anda sudah disetujui admin.',
            'created_at' => '2026-04-24 08:00:00',
        ]);

        UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'laporan_baru',
            'title' => 'Laporan Baru',
            'message' => 'Laporan Anda sudah diterima sistem.',
            'read_at' => now(),
            'created_at' => '2026-04-24 09:00:00',
        ]);

        $response = $this->actingAs($user)
            ->get(route('user.settings.history', [
                'status' => 'unread',
                'type' => 'klaim_disetujui',
                'date' => '2026-04-24',
                'search' => 'Disetujui',
            ]));

        $response->assertOk();
        $response->assertSee('Log / Riwayat');
        $response->assertSee('Klaim Disetujui');
        $response->assertViewHas('statusFilter', 'unread');
        $response->assertViewHas('typeFilter', 'klaim_disetujui');
        $response->assertViewHas('dateFilter', '2026-04-24');
        $response->assertViewHas('search', 'Disetujui');
    }

    private function createUser(
        string $email = 'user-settings@example.com',
        string $username = 'user-settings',
        string $phone = '081111111115'
    ): User {
        $user = User::query()->create([
            'name' => 'User Settings',
            'nama' => 'User Settings',
            'username' => $username,
            'email' => $email,
            'nomor_telepon' => $phone,
            'password' => 'password123',
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
