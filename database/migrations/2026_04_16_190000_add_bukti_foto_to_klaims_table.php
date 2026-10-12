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
            if (! Schema::hasColumn('klaims', 'bukti_foto')) {
                $table->text('bukti_foto')->nullable()->after('catatan');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('klaims')) {
            return;
        }

        Schema::table('klaims', function (Blueprint $table) {
            if (Schema::hasColumn('klaims', 'bukti_foto')) {
                $table->dropColumn('bukti_foto');
            }
        });
    }
};
