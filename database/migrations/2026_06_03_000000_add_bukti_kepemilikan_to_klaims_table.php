<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('klaims') || Schema::hasColumn('klaims', 'bukti_kepemilikan')) {
            return;
        }

        Schema::table('klaims', function (Blueprint $table): void {
            $table->text('bukti_kepemilikan')->nullable()->after('bukti_foto');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('klaims') || ! Schema::hasColumn('klaims', 'bukti_kepemilikan')) {
            return;
        }

        Schema::table('klaims', function (Blueprint $table): void {
            $table->dropColumn('bukti_kepemilikan');
        });
    }
};
