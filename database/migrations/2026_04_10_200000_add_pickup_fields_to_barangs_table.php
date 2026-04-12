<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('barangs')) {
            return;
        }

        Schema::table('barangs', function (Blueprint $table) {
            if (!Schema::hasColumn('barangs', 'lokasi_pengambilan')) {
                $table->string('lokasi_pengambilan')->nullable()->after('foto_barang');
            }

            if (!Schema::hasColumn('barangs', 'alamat_pengambilan')) {
                $table->string('alamat_pengambilan')->nullable()->after('lokasi_pengambilan');
            }

            if (!Schema::hasColumn('barangs', 'penanggung_jawab_pengambilan')) {
                $table->string('penanggung_jawab_pengambilan')->nullable()->after('alamat_pengambilan');
            }

            if (!Schema::hasColumn('barangs', 'kontak_pengambilan')) {
                $table->string('kontak_pengambilan')->nullable()->after('penanggung_jawab_pengambilan');
            }

            if (!Schema::hasColumn('barangs', 'jam_layanan_pengambilan')) {
                $table->string('jam_layanan_pengambilan')->nullable()->after('kontak_pengambilan');
            }

            if (!Schema::hasColumn('barangs', 'catatan_pengambilan')) {
                $table->text('catatan_pengambilan')->nullable()->after('jam_layanan_pengambilan');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('barangs')) {
            return;
        }

        Schema::table('barangs', function (Blueprint $table) {
            $dropColumns = [];
            foreach ([
                'lokasi_pengambilan',
                'alamat_pengambilan',
                'penanggung_jawab_pengambilan',
                'kontak_pengambilan',
                'jam_layanan_pengambilan',
                'catatan_pengambilan',
            ] as $column) {
                if (Schema::hasColumn('barangs', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
