<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('klaims')) {
            return;
        }

        Schema::table('klaims', function (Blueprint $table) {
            if (! Schema::hasColumn('klaims', 'bukti_ciri_khusus')) {
                $table->text('bukti_ciri_khusus')->nullable()->after('bukti_foto');
            }
            if (! Schema::hasColumn('klaims', 'bukti_detail_isi')) {
                $table->text('bukti_detail_isi')->nullable()->after('bukti_ciri_khusus');
            }
            if (! Schema::hasColumn('klaims', 'bukti_lokasi_spesifik')) {
                $table->string('bukti_lokasi_spesifik', 255)->nullable()->after('bukti_detail_isi');
            }
            if (! Schema::hasColumn('klaims', 'bukti_waktu_hilang')) {
                $table->time('bukti_waktu_hilang')->nullable()->after('bukti_lokasi_spesifik');
            }
            if (! Schema::hasColumn('klaims', 'hasil_checklist')) {
                $table->json('hasil_checklist')->nullable()->after('status_verifikasi');
            }
            if (! Schema::hasColumn('klaims', 'skor_validitas')) {
                $table->unsignedTinyInteger('skor_validitas')->nullable()->after('hasil_checklist');
            }
            if (! Schema::hasColumn('klaims', 'catatan_verifikasi_admin')) {
                $table->text('catatan_verifikasi_admin')->nullable()->after('skor_validitas');
            }
            if (! Schema::hasColumn('klaims', 'alasan_penolakan')) {
                $table->text('alasan_penolakan')->nullable()->after('catatan_verifikasi_admin');
            }
            if (! Schema::hasColumn('klaims', 'diverifikasi_at')) {
                $table->timestamp('diverifikasi_at')->nullable()->after('alasan_penolakan');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('klaims')) {
            return;
        }

        Schema::table('klaims', function (Blueprint $table) {
            foreach ([
                'bukti_ciri_khusus',
                'bukti_detail_isi',
                'bukti_lokasi_spesifik',
                'bukti_waktu_hilang',
                'hasil_checklist',
                'skor_validitas',
                'catatan_verifikasi_admin',
                'alasan_penolakan',
                'diverifikasi_at',
            ] as $column) {
                if (Schema::hasColumn('klaims', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
