<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('laporan_barang_hilangs') && !Schema::hasColumn('laporan_barang_hilangs', 'tampil_di_home')) {
            Schema::table('laporan_barang_hilangs', function (Blueprint $table) {
                $table->boolean('tampil_di_home')->default(false)->after('foto_barang');
            });
        }

        if (Schema::hasTable('barangs') && !Schema::hasColumn('barangs', 'tampil_di_home')) {
            Schema::table('barangs', function (Blueprint $table) {
                $table->boolean('tampil_di_home')->default(false)->after('foto_barang');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('laporan_barang_hilangs') && Schema::hasColumn('laporan_barang_hilangs', 'tampil_di_home')) {
            Schema::table('laporan_barang_hilangs', function (Blueprint $table) {
                $table->dropColumn('tampil_di_home');
            });
        }

        if (Schema::hasTable('barangs') && Schema::hasColumn('barangs', 'tampil_di_home')) {
            Schema::table('barangs', function (Blueprint $table) {
                $table->dropColumn('tampil_di_home');
            });
        }
    }
};

