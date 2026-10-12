<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('laporan_barang_hilangs')) {
            return;
        }

        Schema::table('laporan_barang_hilangs', function (Blueprint $table) {
            if (! Schema::hasColumn('laporan_barang_hilangs', 'kategori_barang')) {
                $table->string('kategori_barang', 100)->nullable()->after('nama_barang');
            }
            if (! Schema::hasColumn('laporan_barang_hilangs', 'warna_barang')) {
                $table->string('warna_barang', 100)->nullable()->after('kategori_barang');
            }
            if (! Schema::hasColumn('laporan_barang_hilangs', 'merek_barang')) {
                $table->string('merek_barang', 120)->nullable()->after('warna_barang');
            }
            if (! Schema::hasColumn('laporan_barang_hilangs', 'waktu_hilang')) {
                $table->time('waktu_hilang')->nullable()->after('tanggal_hilang');
            }
            if (! Schema::hasColumn('laporan_barang_hilangs', 'detail_lokasi_hilang')) {
                $table->text('detail_lokasi_hilang')->nullable()->after('lokasi_hilang');
            }
            if (! Schema::hasColumn('laporan_barang_hilangs', 'ciri_khusus')) {
                $table->text('ciri_khusus')->nullable()->after('keterangan');
            }
            if (! Schema::hasColumn('laporan_barang_hilangs', 'kontak_pelapor')) {
                $table->string('kontak_pelapor', 50)->nullable()->after('ciri_khusus');
            }
            if (! Schema::hasColumn('laporan_barang_hilangs', 'bukti_kepemilikan')) {
                $table->text('bukti_kepemilikan')->nullable()->after('kontak_pelapor');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('laporan_barang_hilangs')) {
            return;
        }

        Schema::table('laporan_barang_hilangs', function (Blueprint $table) {
            $columns = [
                'kategori_barang',
                'warna_barang',
                'merek_barang',
                'waktu_hilang',
                'detail_lokasi_hilang',
                'ciri_khusus',
                'kontak_pelapor',
                'bukti_kepemilikan',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('laporan_barang_hilangs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
