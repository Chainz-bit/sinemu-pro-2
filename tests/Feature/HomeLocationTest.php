<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SuperAdmin;
use App\Models\Wilayah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class HomeLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_active_admins_exposes_no_pickup_locations(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('pickupLocationsData', false);
        $this->assertSame([], $response->viewData('pickupLocations'));
    }

    public function test_pending_rejected_and_inactive_admins_are_not_pickup_locations(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $region = $this->createRegion();

        foreach ([Admin::STATUS_PENDING, Admin::STATUS_REJECTED, Admin::STATUS_INACTIVE] as $status) {
            $this->createAdmin($superAdmin, [
                'region_id' => $region->id,
                'status_verifikasi' => $status,
            ]);
        }

        $response = $this->get(route('home'));

        $response->assertOk();
        $this->assertSame([], $response->viewData('pickupLocations'));
    }

    public function test_soft_deleted_active_admin_is_not_pickup_location(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $region = $this->createRegion();

        $admin = $this->createAdmin($superAdmin, [
            'region_id' => $region->id,
            'status_verifikasi' => Admin::STATUS_ACTIVE,
        ]);
        $admin->delete();

        $response = $this->get(route('home'));

        $response->assertOk();
        $this->assertSame([], $response->viewData('pickupLocations'));
    }

    public function test_active_admin_without_region_is_not_pickup_location(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->createAdmin($superAdmin, [
            'region_id' => null,
            'status_verifikasi' => Admin::STATUS_ACTIVE,
            'lat' => -7.111,
            'lng' => 109.111,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $this->assertSame([], $response->viewData('pickupLocations'));
    }

    public function test_active_admin_with_region_without_coordinates_is_not_pickup_location(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $region = $this->createRegion(lat: null, lng: null);

        $this->createAdmin($superAdmin, [
            'region_id' => $region->id,
            'status_verifikasi' => Admin::STATUS_ACTIVE,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $this->assertSame([], $response->viewData('pickupLocations'));
    }

    public function test_active_admin_with_valid_region_coordinates_is_pickup_location(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $region = $this->createRegion(lat: -6.322123, lng: 108.324456);

        $this->createAdmin($superAdmin, [
            'region_id' => $region->id,
            'status_verifikasi' => Admin::STATUS_ACTIVE,
            'instansi' => 'Kampus SINEMU',
        ]);

        $response = $this->get(route('home'));
        $locations = $response->viewData('pickupLocations');

        $response->assertOk();
        $this->assertCount(1, $locations);
        $this->assertSame('Kampus SINEMU', $locations[0]['name']);
        $this->assertSame(-6.322123, $locations[0]['lat']);
        $this->assertSame(108.324456, $locations[0]['lng']);
    }

    public function test_pickup_location_uses_region_coordinates_instead_of_admin_coordinates(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $region = $this->createRegion(lat: -6.333333, lng: 108.333333);

        $this->createAdmin($superAdmin, [
            'region_id' => $region->id,
            'status_verifikasi' => Admin::STATUS_ACTIVE,
            'lat' => -7.111111,
            'lng' => 109.111111,
        ]);

        $response = $this->get(route('home'));
        $locations = $response->viewData('pickupLocations');

        $response->assertOk();
        $this->assertCount(1, $locations);
        $this->assertSame(-6.333333, $locations[0]['lat']);
        $this->assertSame(108.333333, $locations[0]['lng']);
        $this->assertNotSame(-7.111111, $locations[0]['lat']);
        $this->assertNotSame(109.111111, $locations[0]['lng']);
    }

    private function createSuperAdmin(): SuperAdmin
    {
        return SuperAdmin::query()->create([
            'nama' => 'Super Admin Lokasi',
            'email' => 'super-location-' . Str::uuid() . '@example.com',
            'username' => 'super-location-' . Str::random(8),
            'password' => Hash::make('password123'),
        ]);
    }

    private function createAdmin(SuperAdmin $superAdmin, array $overrides = []): Admin
    {
        return Admin::query()->create(array_merge([
            'super_admin_id' => $superAdmin->id,
            'nama' => 'Admin Lokasi',
            'email' => 'admin-location-' . Str::uuid() . '@example.com',
            'username' => 'admin-location-' . Str::random(8),
            'password' => Hash::make('password123'),
            'instansi' => 'Lokasi SiNemu',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Lokasi No. 1',
            'status_verifikasi' => Admin::STATUS_ACTIVE,
            'lat' => -7.111111,
            'lng' => 109.111111,
        ], $overrides));
    }

    private function createRegion(?float $lat = -6.322000, ?float $lng = 108.324000): Wilayah
    {
        return Wilayah::query()->create([
            'nama_wilayah' => 'Kecamatan ' . Str::random(10),
            'lat' => $lat,
            'lng' => $lng,
        ]);
    }
}
