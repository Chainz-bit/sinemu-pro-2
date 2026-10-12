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
        if (! Schema::hasTable('laporan_barang_hilangs')) {
            return;
        }

        Schema::table('laporan_barang_hilangs', function (Blueprint $table) {
            if (! Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan')) {
                $table->enum('sumber_laporan', ['lapor_hilang', 'klaim'])
                    ->default('lapor_hilang')
                    ->after('keterangan');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('laporan_barang_hilangs')) {
            return;
        }

        Schema::table('laporan_barang_hilangs', function (Blueprint $table) {
            if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan')) {
                $table->dropColumn('sumber_laporan');
            }
        });
    }
};
