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
        if (Schema::hasTable('klaims')) {
            Schema::table('klaims', function (Blueprint $table) {
                if (!Schema::hasColumn('klaims', 'nama_penerima')) {
                    $table->string('nama_penerima')->nullable()->after('catatan_verifikasi_admin');
                }
                if (!Schema::hasColumn('klaims', 'nomor_identitas_penerima')) {
                    $table->string('nomor_identitas_penerima')->nullable()->after('nama_penerima');
                }
                if (!Schema::hasColumn('klaims', 'catatan_serah_terima')) {
                    $table->text('catatan_serah_terima')->nullable()->after('nomor_identitas_penerima');
                }
                if (!Schema::hasColumn('klaims', 'foto_serah_terima')) {
                    $table->string('foto_serah_terima')->nullable()->after('catatan_serah_terima');
                }
                if (!Schema::hasColumn('klaims', 'diserahkan_at')) {
                    $table->timestamp('diserahkan_at')->nullable()->after('foto_serah_terima');
                }
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
                $columns = [
                    'nama_penerima',
                    'nomor_identitas_penerima',
                    'catatan_serah_terima',
                    'foto_serah_terima',
                    'diserahkan_at',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('klaims', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
