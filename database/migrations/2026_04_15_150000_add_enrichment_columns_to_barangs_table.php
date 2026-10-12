<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('barangs')) {
            return;
        }

        Schema::table('barangs', function (Blueprint $table) {
            if (! Schema::hasColumn('barangs', 'warna_barang')) {
                $table->string('warna_barang', 100)->nullable()->after('nama_barang');
            }
            if (! Schema::hasColumn('barangs', 'merek_barang')) {
                $table->string('merek_barang', 120)->nullable()->after('warna_barang');
            }
            if (! Schema::hasColumn('barangs', 'nomor_seri')) {
                $table->string('nomor_seri', 150)->nullable()->after('merek_barang');
            }
            if (! Schema::hasColumn('barangs', 'waktu_ditemukan')) {
                $table->time('waktu_ditemukan')->nullable()->after('tanggal_ditemukan');
            }
            if (! Schema::hasColumn('barangs', 'detail_lokasi_ditemukan')) {
                $table->text('detail_lokasi_ditemukan')->nullable()->after('lokasi_ditemukan');
            }
            if (! Schema::hasColumn('barangs', 'ciri_khusus')) {
                $table->text('ciri_khusus')->nullable()->after('deskripsi');
            }
            if (! Schema::hasColumn('barangs', 'nama_penemu')) {
                $table->string('nama_penemu', 150)->nullable()->after('ciri_khusus');
            }
            if (! Schema::hasColumn('barangs', 'kontak_penemu')) {
                $table->string('kontak_penemu', 50)->nullable()->after('nama_penemu');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('barangs')) {
            return;
        }

        Schema::table('barangs', function (Blueprint $table) {
            $columns = [
                'warna_barang',
                'merek_barang',
                'nomor_seri',
                'waktu_ditemukan',
                'detail_lokasi_ditemukan',
                'ciri_khusus',
                'nama_penemu',
                'kontak_penemu',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('barangs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
