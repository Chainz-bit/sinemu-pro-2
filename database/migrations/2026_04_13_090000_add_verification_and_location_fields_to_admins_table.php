<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            if (!Schema::hasColumn('admins', 'kecamatan')) {
                $table->string('kecamatan', 100)->nullable()->after('instansi');
            }

            if (!Schema::hasColumn('admins', 'alamat_lengkap')) {
                $table->text('alamat_lengkap')->nullable()->after('kecamatan');
            }

            if (!Schema::hasColumn('admins', 'status_verifikasi')) {
                $table->enum('status_verifikasi', ['pending', 'active', 'rejected'])
                    ->default('pending')
                    ->after('alamat_lengkap');
            }

            if (!Schema::hasColumn('admins', 'alasan_penolakan')) {
                $table->text('alasan_penolakan')->nullable()->after('status_verifikasi');
            }

            if (!Schema::hasColumn('admins', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('alasan_penolakan');
            }

            if (!Schema::hasColumn('admins', 'lat')) {
                $table->decimal('lat', 10, 7)->nullable()->after('verified_at');
            }

            if (!Schema::hasColumn('admins', 'lng')) {
                $table->decimal('lng', 10, 7)->nullable()->after('lat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            foreach (['lng', 'lat', 'verified_at', 'alasan_penolakan', 'status_verifikasi', 'alamat_lengkap', 'kecamatan'] as $column) {
                if (Schema::hasColumn('admins', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
