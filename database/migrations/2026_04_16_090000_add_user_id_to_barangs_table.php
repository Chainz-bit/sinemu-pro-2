<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barangs', function (Blueprint $table) {
            if (! Schema::hasColumn('barangs', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('admin_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        if (Schema::hasColumn('barangs', 'user_id')) {
            // Backfill ringan: cocokkan kontak penemu dengan nomor telepon user.
            if (Schema::hasColumn('barangs', 'kontak_penemu') && Schema::hasColumn('users', 'nomor_telepon')) {
                DB::statement("
                    UPDATE barangs b
                    JOIN users u ON u.nomor_telepon IS NOT NULL
                        AND TRIM(u.nomor_telepon) <> ''
                        AND TRIM(b.kontak_penemu) = TRIM(u.nomor_telepon)
                    SET b.user_id = u.id
                    WHERE b.user_id IS NULL
                ");
            }
        }
    }

    public function down(): void
    {
        Schema::table('barangs', function (Blueprint $table) {
            if (Schema::hasColumn('barangs', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
        });
    }
};
