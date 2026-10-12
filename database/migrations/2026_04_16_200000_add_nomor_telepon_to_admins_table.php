<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            if (! Schema::hasColumn('admins', 'nomor_telepon')) {
                $table->string('nomor_telepon', 50)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admins') || ! Schema::hasColumn('admins', 'nomor_telepon')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('nomor_telepon');
        });
    }
};
