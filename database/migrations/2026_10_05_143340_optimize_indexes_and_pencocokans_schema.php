<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tabel pencocokans: ubah admin_id menjadi nullable
        if (Schema::hasTable('pencocokans') && Schema::hasColumn('pencocokans', 'admin_id')) {
            Schema::table('pencocokans', function (Blueprint $table) {
                $table->unsignedBigInteger('admin_id')->nullable()->change();
            });
        }

        // 2. Tabel barangs: tambahkan indeks status dan tanggal ditemukan
        if (Schema::hasTable('barangs')) {
            Schema::table('barangs', function (Blueprint $table) {
                $table->index(['status_barang', 'status_laporan'], 'idx_barangs_status');
                $table->index('tanggal_ditemukan', 'idx_barangs_tanggal_ditemukan');
            });
        }

        // 3. Tabel laporan_barang_hilangs: tambahkan indeks status+region dan tanggal hilang
        if (Schema::hasTable('laporan_barang_hilangs')) {
            Schema::table('laporan_barang_hilangs', function (Blueprint $table) {
                $table->index(['status_laporan', 'region_id'], 'idx_laporan_status_region');
                $table->index('tanggal_hilang', 'idx_laporan_tanggal_hilang');
            });
        }

        // 4. Tabel klaims: tambahkan indeks barang_id + status_verifikasi
        if (Schema::hasTable('klaims')) {
            Schema::table('klaims', function (Blueprint $table) {
                $table->index(['barang_id', 'status_verifikasi'], 'idx_klaims_barang_verifikasi');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('klaims')) {
            Schema::table('klaims', function (Blueprint $table) {
                $table->dropIndex('idx_klaims_barang_verifikasi');
            });
        }

        if (Schema::hasTable('laporan_barang_hilangs')) {
            Schema::table('laporan_barang_hilangs', function (Blueprint $table) {
                $table->dropIndex('idx_laporan_tanggal_hilang');
                $table->dropIndex('idx_laporan_status_region');
            });
        }

        if (Schema::hasTable('barangs')) {
            Schema::table('barangs', function (Blueprint $table) {
                $table->dropIndex('idx_barangs_tanggal_ditemukan');
                $table->dropIndex('idx_barangs_status');
            });
        }

        if (Schema::hasTable('pencocokans') && Schema::hasColumn('pencocokans', 'admin_id')) {
            Schema::table('pencocokans', function (Blueprint $table) {
                $table->unsignedBigInteger('admin_id')->nullable(false)->change();
            });
        }
    }
};
