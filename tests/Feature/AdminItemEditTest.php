<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Models\Pencocokan;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Models\Wilayah;
use App\Support\WorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminItemEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_found_item_edit_page(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $barang = $this->createFoundItem($admin, $user, $kategori);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.found-items.edit', $barang))
            ->assertOk()
            ->assertSee('Edit Data Barang Temuan')
            ->assertSee($barang->nama_barang);
    }

    public function test_admin_can_update_found_item(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $barang = $this->createFoundItem($admin, $user, $kategori);

        $response = $this->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.update', $barang), $this->validFoundItemPayload($kategori));

        $response->assertRedirect(route('admin.found-items.show', $barang));
        $response->assertSessionHas('status', 'Data barang temuan berhasil diperbarui.');

        $barang = $barang->fresh();

        $this->assertSame('Laptop ASUS', $barang?->nama_barang);
        $this->assertSame('Ruang Dosen', $barang?->lokasi_ditemukan);
        $this->assertSame('2026-04-20', $barang?->tanggal_ditemukan);
    }

    #[DataProvider('editableFoundReportStatuses')]
    public function test_admin_can_update_editable_found_item_statuses(string $status): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $barang = $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => $status,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'tampil_di_home' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.update', $barang), $this->validFoundItemPayload($kategori))
            ->assertRedirect(route('admin.found-items.show', $barang))
            ->assertSessionHas('status', 'Data barang temuan berhasil diperbarui.');

        $this->assertSame('Laptop ASUS', $barang->fresh()?->nama_barang);
    }

    public function test_found_item_update_validates_required_fields(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $barang = $this->createFoundItem($admin, $user, $kategori);

        $response = $this->from(route('admin.found-items.edit', $barang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.update', $barang), [
                'kategori_id' => $kategori->id,
                'deskripsi' => 'Payload tanpa field wajib.',
            ]);

        $response->assertRedirect(route('admin.found-items.edit', $barang));
        $response->assertSessionHasErrors([
            'nama_barang',
            'lokasi_ditemukan',
            'tanggal_ditemukan',
        ]);

        $this->assertSame('Tas Abu-abu', $barang->fresh()?->nama_barang);
    }

    #[DataProvider('lockedFoundItemStates')]
    public function test_locked_found_item_edit_page_is_rejected(array $overrides): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $barang = $this->createFoundItem($admin, $user, $kategori, $overrides);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.found-items.edit', $barang))
            ->assertRedirect(route('admin.found-items.show', $barang))
            ->assertSessionHas('error', 'Barang temuan ini tidak dapat diedit karena sudah diproses.');
    }

    #[DataProvider('lockedFoundItemStates')]
    public function test_locked_found_item_update_is_rejected_without_changing_fields(array $overrides): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $barang = $this->createFoundItem($admin, $user, $kategori, $overrides);

        $this->from(route('admin.found-items.show', $barang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.update', $barang), $this->validFoundItemPayload($kategori))
            ->assertRedirect(route('admin.found-items.show', $barang))
            ->assertSessionHas('error', 'Barang temuan ini tidak dapat diedit karena sudah diproses.');

        $barang = $barang->fresh();

        $this->assertSame('Tas Abu-abu', $barang?->nama_barang);
        $this->assertSame('Koridor Kampus', $barang?->lokasi_ditemukan);
        $this->assertSame('2026-04-17', $barang?->tanggal_ditemukan);
        $this->assertSame('Ditemukan di lorong kampus.', $barang?->deskripsi);
    }

    public function test_locked_found_item_update_does_not_replace_or_delete_photo(): void
    {
        Storage::fake('public');

        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $originalPhoto = 'barang-temuan/2026/04/original.jpg';
        Storage::disk('public')->put($originalPhoto, 'foto asli');

        $barang = $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'foto_barang' => $originalPhoto,
            'tampil_di_home' => false,
        ]);

        $payload = array_merge($this->validFoundItemPayload($kategori), [
            'foto_barang' => UploadedFile::fake()->createWithContent(
                'pengganti.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')
            ),
        ]);

        $this->from(route('admin.found-items.show', $barang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.update', $barang), $payload)
            ->assertRedirect(route('admin.found-items.show', $barang))
            ->assertSessionHas('error', 'Barang temuan ini tidak dapat diedit karena sudah diproses.');

        $barang = $barang->fresh();
        $files = Storage::disk('public')->allFiles('barang-temuan');
        sort($files);

        $this->assertSame($originalPhoto, $barang?->foto_barang);
        Storage::disk('public')->assertExists($originalPhoto);
        $this->assertSame([$originalPhoto], $files);
    }

    public function test_approved_found_item_with_claim_cannot_be_edited(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $laporanBarangHilang = $this->createLostItem($user, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);
        $barang = $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'tampil_di_home' => false,
        ]);

        $this->createClaim($admin, $user, $laporanBarangHilang, $barang);

        $this->from(route('admin.found-items.show', $barang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.update', $barang), $this->validFoundItemPayload($kategori))
            ->assertRedirect(route('admin.found-items.show', $barang))
            ->assertSessionHas('error', 'Barang temuan ini tidak dapat diedit karena sudah diproses.');

        $this->assertSame('Tas Abu-abu', $barang->fresh()?->nama_barang);
    }

    public function test_approved_found_item_with_matching_cannot_be_edited(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $laporanBarangHilang = $this->createLostItem($user, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);
        $barang = $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'tampil_di_home' => false,
        ]);

        Pencocokan::query()->create([
            'laporan_hilang_id' => $laporanBarangHilang->id,
            'barang_id' => $barang->id,
            'admin_id' => $admin->id,
            'status_pencocokan' => WorkflowStatus::MATCH_CONFIRMED,
            'catatan' => 'Barang temuan terkait pencocokan.',
            'matched_at' => now(),
        ]);

        $this->from(route('admin.found-items.show', $barang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.update', $barang), $this->validFoundItemPayload($kategori))
            ->assertRedirect(route('admin.found-items.show', $barang))
            ->assertSessionHas('error', 'Barang temuan ini tidak dapat diedit karena sudah diproses.');

        $this->assertSame('Tas Abu-abu', $barang->fresh()?->nama_barang);
    }

    public function test_found_item_visible_on_home_cannot_be_edited(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $barang = $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'tampil_di_home' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.found-items.edit', $barang))
            ->assertRedirect(route('admin.found-items.show', $barang))
            ->assertSessionHas('error', 'Barang temuan ini tidak dapat diedit karena sudah diproses.');

        $this->from(route('admin.found-items.show', $barang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.update', $barang), $this->validFoundItemPayload($kategori))
            ->assertRedirect(route('admin.found-items.show', $barang))
            ->assertSessionHas('error', 'Barang temuan ini tidak dapat diedit karena sudah diproses.');

        $this->assertSame('Tas Abu-abu', $barang->fresh()?->nama_barang);
    }

    public function test_admin_can_open_lost_item_edit_page(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $laporanBarangHilang = $this->createLostItem($user);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.lost-items.edit', $laporanBarangHilang))
            ->assertOk()
            ->assertSee('Edit Data Barang Hilang')
            ->assertSee($laporanBarangHilang->nama_barang);
    }

    public function test_lost_item_edit_redirects_for_non_lapor_hilang_source(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();

        $laporanBarangHilang = $this->createLostItem($user, [
            'sumber_laporan' => 'claim',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.lost-items.edit', $laporanBarangHilang))
            ->assertRedirect(route('admin.lost-items'));
    }

    public function test_admin_can_update_lost_item(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();

        $laporanBarangHilang = $this->createLostItem($user);

        $response = $this->actingAs($admin, 'admin')
            ->patch(route('admin.lost-items.update', $laporanBarangHilang), $this->validLostItemPayload());

        $response->assertRedirect(route('admin.lost-items.show', $laporanBarangHilang));
        $response->assertSessionHas('status', 'Data barang hilang berhasil diperbarui.');

        $laporanBarangHilang = $laporanBarangHilang->fresh();

        $this->assertSame('Dompet Hitam', $laporanBarangHilang?->nama_barang);
        $this->assertSame('Gedung Serbaguna', $laporanBarangHilang?->lokasi_hilang);
        $this->assertSame('2026-04-18', $laporanBarangHilang?->tanggal_hilang);
    }

    public function test_admin_can_update_submitted_lost_item(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();

        $laporanBarangHilang = $this->createLostItem($user, [
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'tampil_di_home' => false,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->patch(route('admin.lost-items.update', $laporanBarangHilang), $this->validLostItemPayload());

        $response->assertRedirect(route('admin.lost-items.show', $laporanBarangHilang));
        $response->assertSessionHas('status', 'Data barang hilang berhasil diperbarui.');

        $laporanBarangHilang = $laporanBarangHilang->fresh();

        $this->assertSame('Dompet Hitam', $laporanBarangHilang?->nama_barang);
        $this->assertSame('Gedung Serbaguna', $laporanBarangHilang?->lokasi_hilang);
    }

    public function test_lost_item_update_validates_required_fields(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();

        $laporanBarangHilang = $this->createLostItem($user);

        $response = $this->from(route('admin.lost-items.edit', $laporanBarangHilang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.lost-items.update', $laporanBarangHilang), [
                'kategori_barang' => 'Aksesoris',
            ]);

        $response->assertRedirect(route('admin.lost-items.edit', $laporanBarangHilang));
        $response->assertSessionHasErrors([
            'nama_barang',
            'lokasi_hilang',
            'tanggal_hilang',
            'keterangan',
        ]);

        $this->assertSame('Dompet Cokelat', $laporanBarangHilang->fresh()?->nama_barang);
    }

    #[DataProvider('lockedLostReportStatuses')]
    public function test_locked_lost_item_edit_page_is_rejected(string $status): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $laporanBarangHilang = $this->createLostItem($user, [
            'status_laporan' => $status,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.lost-items.edit', $laporanBarangHilang))
            ->assertRedirect(route('admin.lost-items.show', $laporanBarangHilang))
            ->assertSessionHas('error', 'Laporan ini tidak dapat diedit karena sudah diproses.');
    }

    #[DataProvider('lockedLostReportStatuses')]
    public function test_locked_lost_item_update_is_rejected_without_changing_fields(string $status): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $laporanBarangHilang = $this->createLostItem($user, [
            'status_laporan' => $status,
        ]);

        $response = $this->from(route('admin.lost-items.show', $laporanBarangHilang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.lost-items.update', $laporanBarangHilang), $this->validLostItemPayload());

        $response->assertRedirect(route('admin.lost-items.show', $laporanBarangHilang));
        $response->assertSessionHas('error', 'Laporan ini tidak dapat diedit karena sudah diproses.');

        $laporanBarangHilang = $laporanBarangHilang->fresh();

        $this->assertSame('Dompet Cokelat', $laporanBarangHilang?->nama_barang);
        $this->assertSame('Kantin Kampus', $laporanBarangHilang?->lokasi_hilang);
        $this->assertSame('2026-04-16', $laporanBarangHilang?->tanggal_hilang);
        $this->assertSame('Berisi kartu mahasiswa dan SIM.', $laporanBarangHilang?->keterangan);
    }

    public function test_locked_lost_item_update_does_not_replace_or_delete_photo(): void
    {
        Storage::fake('public');

        $admin = $this->createAdmin();
        $user = $this->createUser();
        $originalPhoto = 'barang-hilang/2026/04/original.jpg';
        Storage::disk('public')->put($originalPhoto, 'foto asli');

        $laporanBarangHilang = $this->createLostItem($user, [
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'foto_barang' => $originalPhoto,
        ]);

        $payload = array_merge($this->validLostItemPayload(), [
            'foto_barang' => UploadedFile::fake()->createWithContent(
                'pengganti.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')
            ),
        ]);

        $response = $this->from(route('admin.lost-items.show', $laporanBarangHilang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.lost-items.update', $laporanBarangHilang), $payload);

        $response->assertRedirect(route('admin.lost-items.show', $laporanBarangHilang));
        $response->assertSessionHas('error', 'Laporan ini tidak dapat diedit karena sudah diproses.');

        $laporanBarangHilang = $laporanBarangHilang->fresh();
        $files = Storage::disk('public')->allFiles('barang-hilang');
        sort($files);

        $this->assertSame($originalPhoto, $laporanBarangHilang?->foto_barang);
        Storage::disk('public')->assertExists($originalPhoto);
        $this->assertSame([$originalPhoto], $files);
    }

    public function test_approved_lost_item_with_claim_cannot_be_edited(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $laporanBarangHilang = $this->createLostItem($user);
        $barang = $this->createFoundItem($admin, $user, $kategori);

        $this->createClaim($admin, $user, $laporanBarangHilang, $barang);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.lost-items.edit', $laporanBarangHilang))
            ->assertRedirect(route('admin.lost-items.show', $laporanBarangHilang))
            ->assertSessionHas('error', 'Laporan ini tidak dapat diedit karena sudah diproses.');

        $this->from(route('admin.lost-items.show', $laporanBarangHilang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.lost-items.update', $laporanBarangHilang), $this->validLostItemPayload())
            ->assertRedirect(route('admin.lost-items.show', $laporanBarangHilang))
            ->assertSessionHas('error', 'Laporan ini tidak dapat diedit karena sudah diproses.');

        $this->assertSame('Dompet Cokelat', $laporanBarangHilang->fresh()?->nama_barang);
    }

    public function test_approved_lost_item_with_matching_cannot_be_edited(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $laporanBarangHilang = $this->createLostItem($user);
        $barang = $this->createFoundItem($admin, $user, $kategori);

        Pencocokan::query()->create([
            'laporan_hilang_id' => $laporanBarangHilang->id,
            'barang_id' => $barang->id,
            'admin_id' => $admin->id,
            'status_pencocokan' => WorkflowStatus::MATCH_CONFIRMED,
            'catatan' => 'Diduga kuat cocok.',
            'matched_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.lost-items.edit', $laporanBarangHilang))
            ->assertRedirect(route('admin.lost-items.show', $laporanBarangHilang))
            ->assertSessionHas('error', 'Laporan ini tidak dapat diedit karena sudah diproses.');

        $this->from(route('admin.lost-items.show', $laporanBarangHilang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.lost-items.update', $laporanBarangHilang), $this->validLostItemPayload())
            ->assertRedirect(route('admin.lost-items.show', $laporanBarangHilang))
            ->assertSessionHas('error', 'Laporan ini tidak dapat diedit karena sudah diproses.');

        $this->assertSame('Dompet Cokelat', $laporanBarangHilang->fresh()?->nama_barang);
    }

    public function test_lost_item_index_hides_edit_data_menu_for_locked_report(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();

        $this->createLostItem($user, [
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.lost-items'))
            ->assertOk()
            ->assertSee('Lihat Detail')
            ->assertDontSee('Edit Data');
    }

    public function test_found_item_index_hides_edit_data_menu_for_locked_item(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
            'tampil_di_home' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.found-items'))
            ->assertOk()
            ->assertSee('Lihat Detail')
            ->assertDontSee('Edit Data');
    }

    public function test_found_item_index_shows_edit_data_menu_for_editable_item(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'tampil_di_home' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.found-items'))
            ->assertOk()
            ->assertSee('Lihat Detail')
            ->assertSee('Edit Data');
    }

    public function test_found_item_index_shows_publish_home_menu_for_eligible_item(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'tampil_di_home' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.found-items'))
            ->assertOk()
            ->assertSee('Tampilkan di Home')
            ->assertDontSee('Upload');
    }

    public function test_found_item_index_hides_publish_home_menu_for_locked_item(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
            'tampil_di_home' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.found-items'))
            ->assertOk()
            ->assertSee('Lihat Detail')
            ->assertDontSee('Tampilkan di Home')
            ->assertDontSee('Upload');
    }

    public function test_found_item_index_shows_home_note_without_publish_action_for_published_item(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'tampil_di_home' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.found-items'))
            ->assertOk()
            ->assertSee('Sudah tampil di Home')
            ->assertDontSee('Tampilkan di Home')
            ->assertDontSee('Upload');
    }

    #[DataProvider('lostItemIndexActionMenuProvider')]
    public function test_lost_item_index_action_menu_visibility_matches_report_status(
        string $status,
        bool $tampilDiHome,
        bool $expectsEdit,
        bool $expectsUpload,
        bool $expectsDelete
    ): void {
        $admin = $this->createAdmin();
        $user = $this->createUser();

        $this->createLostItem($user, [
            'nama_barang' => 'Menu Aksi ' . $status,
            'status_laporan' => $status,
            'tampil_di_home' => $tampilDiHome,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.lost-items'))
            ->assertOk()
            ->assertSee('Lihat Detail');

        $expectsEdit
            ? $response->assertSee('Edit Data')
            : $response->assertDontSee('Edit Data');

        $expectsUpload
            ? $response->assertSee('Tampilkan di Home')
            : $response->assertDontSee('Tampilkan di Home');

        $expectsDelete
            ? $response->assertSee('<button type="submit" class="menu-submit danger">Hapus</button>', false)
            : $response->assertDontSee('<button type="submit" class="menu-submit danger">Hapus</button>', false);
    }

    public function test_admin_from_other_region_cannot_edit_lost_report(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $laporanBarangHilang = $this->createLostItem($user);
        $otherRegion = Wilayah::query()->create([
            'nama_wilayah' => 'Wilayah Edit Lain',
            'lat' => -6.41,
            'lng' => 108.41,
        ]);
        $otherAdmin = $this->createAdminForRegion($otherRegion, 'other-region');

        $this->actingAs($otherAdmin, 'admin')
            ->get(route('admin.lost-items.edit', $laporanBarangHilang))
            ->assertForbidden();

        $this->actingAs($otherAdmin, 'admin')
            ->patch(route('admin.lost-items.update', $laporanBarangHilang), $this->validLostItemPayload())
            ->assertForbidden();

        $this->assertSame($admin->region_id, $laporanBarangHilang->fresh()?->region_id);
        $this->assertSame('Dompet Cokelat', $laporanBarangHilang->fresh()?->nama_barang);
    }

    public function test_admin_from_other_region_cannot_edit_found_item(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $barang = $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'tampil_di_home' => false,
        ]);
        $otherRegion = Wilayah::query()->create([
            'nama_wilayah' => 'Wilayah Edit Temuan Lain',
            'lat' => -6.41,
            'lng' => 108.41,
        ]);
        $otherAdmin = $this->createAdminForRegion($otherRegion, 'found-other-region');

        $this->actingAs($otherAdmin, 'admin')
            ->get(route('admin.found-items.edit', $barang))
            ->assertForbidden();

        $this->actingAs($otherAdmin, 'admin')
            ->patch(route('admin.found-items.update', $barang), $this->validFoundItemPayload($kategori))
            ->assertForbidden();

        $this->assertSame($admin->region_id, $barang->fresh()?->region_id);
        $this->assertSame('Tas Abu-abu', $barang->fresh()?->nama_barang);
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function lockedLostReportStatuses(): array
    {
        return [
            'matched' => [WorkflowStatus::REPORT_MATCHED],
            'claimed' => [WorkflowStatus::REPORT_CLAIMED],
            'completed' => [WorkflowStatus::REPORT_COMPLETED],
            'rejected' => [WorkflowStatus::REPORT_REJECTED],
        ];
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function editableFoundReportStatuses(): array
    {
        return [
            'pending' => ['pending'],
            'submitted' => [WorkflowStatus::REPORT_SUBMITTED],
            'menunggu' => ['menunggu'],
            'approved tersedia' => [WorkflowStatus::REPORT_APPROVED],
        ];
    }

    /**
     * @return array<string,array{0:array<string,mixed>}>
     */
    public static function lockedFoundItemStates(): array
    {
        return [
            'matched report' => [[
                'status_laporan' => WorkflowStatus::REPORT_MATCHED,
                'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
                'tampil_di_home' => false,
            ]],
            'dalam proses klaim' => [[
                'status_laporan' => WorkflowStatus::REPORT_APPROVED,
                'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
                'tampil_di_home' => false,
            ]],
            'sudah diklaim' => [[
                'status_laporan' => WorkflowStatus::REPORT_APPROVED,
                'status_barang' => WorkflowStatus::FOUND_CLAIMED,
                'tampil_di_home' => false,
            ]],
            'completed report' => [[
                'status_laporan' => WorkflowStatus::REPORT_COMPLETED,
                'status_barang' => WorkflowStatus::FOUND_RETURNED,
                'tampil_di_home' => false,
            ]],
            'selesai legacy item' => [[
                'status_laporan' => WorkflowStatus::REPORT_APPROVED,
                'status_barang' => 'selesai',
                'tampil_di_home' => false,
            ]],
            'rejected report' => [[
                'status_laporan' => WorkflowStatus::REPORT_REJECTED,
                'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
                'tampil_di_home' => false,
            ]],
            'unknown report status' => [[
                'status_laporan' => 'arsip',
                'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
                'tampil_di_home' => false,
            ]],
        ];
    }

    /**
     * @return array<string,array{0:string,1:bool,2:bool,3:bool,4:bool}>
     */
    public static function lostItemIndexActionMenuProvider(): array
    {
        return [
            'submitted' => [WorkflowStatus::REPORT_SUBMITTED, false, true, false, false],
            'approved unpublished' => [WorkflowStatus::REPORT_APPROVED, false, true, true, false],
            'approved published' => [WorkflowStatus::REPORT_APPROVED, true, true, false, false],
            'matched' => [WorkflowStatus::REPORT_MATCHED, false, false, false, false],
            'claimed' => [WorkflowStatus::REPORT_CLAIMED, false, false, false, false],
            'completed' => [WorkflowStatus::REPORT_COMPLETED, false, false, false, false],
            'rejected' => [WorkflowStatus::REPORT_REJECTED, false, false, false, true],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validFoundItemPayload(Kategori $kategori): array
    {
        return [
            'nama_barang' => 'Laptop ASUS',
            'kategori_id' => $kategori->id,
            'warna_barang' => 'Hitam',
            'merek_barang' => 'ASUS',
            'nomor_seri' => 'SN-FOUND-002',
            'deskripsi' => 'Ditemukan di meja depan ruang dosen.',
            'ciri_khusus' => 'Ada stiker kampus di cover.',
            'nama_penemu' => 'Satpam Kampus',
            'kontak_penemu' => '081200000001',
            'lokasi_ditemukan' => 'Ruang Dosen',
            'detail_lokasi_ditemukan' => 'Dekat meja resepsionis.',
            'tanggal_ditemukan' => '2026-04-20',
            'waktu_ditemukan' => '09:30',
            'lokasi_pengambilan' => 'Pos Keamanan',
            'alamat_pengambilan' => 'Gedung A',
            'penanggung_jawab_pengambilan' => 'Petugas Jaga',
            'kontak_pengambilan' => '081200000002',
            'jam_layanan_pengambilan' => '08:00-16:00',
            'catatan_pengambilan' => 'Tunjukkan kartu identitas.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validLostItemPayload(): array
    {
        return [
            'nama_barang' => 'Dompet Hitam',
            'kategori_barang' => 'Aksesoris',
            'warna_barang' => 'Hitam',
            'merek_barang' => 'Eiger',
            'nomor_seri' => 'N/A',
            'lokasi_hilang' => 'Gedung Serbaguna',
            'detail_lokasi_hilang' => 'Area kursi baris depan.',
            'tanggal_hilang' => '2026-04-18',
            'waktu_hilang' => '13:15',
            'keterangan' => 'Dompet berisi kartu mahasiswa dan uang tunai.',
            'ciri_khusus' => 'Ada gantungan kecil warna merah.',
            'kontak_pelapor' => '081233344455',
            'bukti_kepemilikan' => 'Ada foto keluarga di slot transparan.',
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createFoundItem(Admin $admin, User $user, Kategori $kategori, array $overrides = []): Barang
    {
        return Barang::query()->create(array_merge([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Tas Abu-abu',
            'warna_barang' => 'Abu-abu',
            'merek_barang' => 'Eiger',
            'nomor_seri' => 'SN-FOUND-001',
            'deskripsi' => 'Ditemukan di lorong kampus.',
            'ciri_khusus' => 'Ada gantungan kunci.',
            'nama_penemu' => 'Petugas Kebersihan',
            'kontak_penemu' => '081100000001',
            'lokasi_ditemukan' => 'Koridor Kampus',
            'detail_lokasi_ditemukan' => 'Dekat tangga utama.',
            'tanggal_ditemukan' => '2026-04-17',
            'waktu_ditemukan' => '08:00',
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ], $overrides));
    }

    private function createClaim(Admin $admin, User $user, LaporanBarangHilang $laporanBarangHilang, Barang $barang): Klaim
    {
        return Klaim::query()->create([
            'laporan_hilang_id' => $laporanBarangHilang->id,
            'barang_id' => $barang->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_PENDING,
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
            'catatan' => 'Klaim sedang diproses.',
            'kontak' => '081233344455',
            'bukti_foto' => ['verifikasi-klaim/2026/04/bukti.jpg'],
            'bukti_kepemilikan' => 'Foto keluarga di dalam dompet.',
            'bukti_ciri_khusus' => 'Ada bekas lipatan.',
            'bukti_lokasi_spesifik' => 'Kantin kampus.',
            'bukti_waktu_hilang' => '12:30:00',
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createLostItem(User $user, array $overrides = []): LaporanBarangHilang
    {
        return LaporanBarangHilang::query()->create(array_merge([
            'user_id' => $user->id,
            'region_id' => Wilayah::query()->value('id'),
            'nama_barang' => 'Dompet Cokelat',
            'kategori_barang' => 'Aksesoris',
            'warna_barang' => 'Cokelat',
            'merek_barang' => 'Kulit Lokal',
            'nomor_seri' => 'N/A',
            'lokasi_hilang' => 'Kantin Kampus',
            'detail_lokasi_hilang' => 'Dekat meja kasir.',
            'tanggal_hilang' => '2026-04-16',
            'waktu_hilang' => '12:30',
            'keterangan' => 'Berisi kartu mahasiswa dan SIM.',
            'ciri_khusus' => 'Ada bekas lipatan di sudut kiri.',
            'kontak_pelapor' => '081122233344',
            'bukti_kepemilikan' => 'Foto keluarga di dalam dompet.',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
        ], $overrides));
    }

    private function createUser(): User
    {
        $user = User::query()->create([
            'name' => 'User Edit Admin',
            'nama' => 'User Edit Admin',
            'username' => 'user-edit-admin',
            'email' => 'admin-item-user@example.com',
            'nomor_telepon' => '081111111113',
            'password' => 'password123',
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function createAdmin(): Admin
    {
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super Admin Item',
            'email' => 'admin-item-super@example.com',
            'username' => 'super-item-admin',
            'password' => Hash::make('password123'),
        ]);
        $region = Wilayah::query()->create([
            'nama_wilayah' => 'Wilayah Admin Item',
            'lat' => -6.326,
            'lng' => 108.32,
        ]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'region_id' => $region->id,
            'nama' => 'Admin Item',
            'email' => 'admin-item@example.com',
            'username' => 'admin-item',
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Admin Item No. 1',
            'status_verifikasi' => 'active',
        ]);
    }

    private function createAdminForRegion(Wilayah $region, string $suffix): Admin
    {
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super Admin Item ' . $suffix,
            'email' => 'admin-item-super-' . $suffix . '@example.com',
            'username' => 'super-item-admin-' . $suffix,
            'password' => Hash::make('password123'),
        ]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'region_id' => $region->id,
            'nama' => 'Admin Item ' . $suffix,
            'email' => 'admin-item-' . $suffix . '@example.com',
            'username' => 'admin-item-' . $suffix,
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Admin Item No. ' . $suffix,
            'status_verifikasi' => 'active',
        ]);
    }
}
