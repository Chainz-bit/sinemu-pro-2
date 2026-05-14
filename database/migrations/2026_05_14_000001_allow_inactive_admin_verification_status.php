<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admins') || !Schema::hasColumn('admins', 'status_verifikasi')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->string('status_verifikasi', 30)->default('pending')->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('admins') || !Schema::hasColumn('admins', 'status_verifikasi')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->string('status_verifikasi', 30)->default('pending')->change();
        });
    }
};
