<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('laporan_barang_hilangs') && ! Schema::hasColumn('laporan_barang_hilangs', 'region_id')) {
            Schema::table('laporan_barang_hilangs', function (Blueprint $table) {
                $table->foreignId('region_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('wilayahs')
                    ->nullOnDelete();
                $table->index(['region_id', 'created_at']);
            });
        }

        if (Schema::hasTable('admins') && Schema::hasColumn('admins', 'region_id') && Schema::hasTable('wilayahs')) {
            DB::table('admins')
                ->whereNull('region_id')
                ->whereNotNull('kecamatan')
                ->orderBy('id')
                ->lazyById()
                ->each(function ($admin): void {
                    $district = $this->normalizeDistrictName((string) $admin->kecamatan);
                    if ($district === '') {
                        return;
                    }

                    $regionId = DB::table('wilayahs')
                        ->whereRaw("LOWER(REPLACE(nama_wilayah, 'Kecamatan ', '')) = ?", [Str::lower($district)])
                        ->orWhereRaw('LOWER(nama_wilayah) = ?', [Str::lower('Kecamatan '.$district)])
                        ->value('id');

                    if ($regionId) {
                        DB::table('admins')->where('id', $admin->id)->update(['region_id' => $regionId]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('laporan_barang_hilangs') && Schema::hasColumn('laporan_barang_hilangs', 'region_id')) {
            Schema::table('laporan_barang_hilangs', function (Blueprint $table) {
                $table->dropIndex(['region_id', 'created_at']);
                $table->dropConstrainedForeignId('region_id');
            });
        }
    }

    private function normalizeDistrictName(string $district): string
    {
        $district = trim(preg_replace('/\s+/', ' ', $district) ?? '');
        $normalized = strtolower(str_replace(['-', '_'], ' ', $district));

        return match ($normalized) {
            'indramayu kota', 'kota indramayu' => 'Indramayu',
            'lobener' => 'Lohbener',
            'kedokanbunder', 'kedokan bunder' => 'Kedokan Bunder',
            default => ucwords($normalized),
        };
    }
};
