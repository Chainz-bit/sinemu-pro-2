<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('laporan_barang_hilangs')) {
            return;
        }

        Schema::table('laporan_barang_hilangs', function (Blueprint $table) {
            if (!Schema::hasColumn('laporan_barang_hilangs', 'foto_barang')) {
                $table->string('foto_barang')->nullable()->after('keterangan');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('laporan_barang_hilangs')) {
            return;
        }

        Schema::table('laporan_barang_hilangs', function (Blueprint $table) {
            if (Schema::hasColumn('laporan_barang_hilangs', 'foto_barang')) {
                $table->dropColumn('foto_barang');
            }
        });
    }
};
