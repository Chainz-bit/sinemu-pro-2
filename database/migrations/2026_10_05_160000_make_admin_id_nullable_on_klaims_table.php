<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('klaims') && Schema::hasColumn('klaims', 'admin_id')) {
            Schema::table('klaims', function (Blueprint $table) {
                $table->unsignedBigInteger('admin_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('klaims') && Schema::hasColumn('klaims', 'admin_id')) {
            Schema::table('klaims', function (Blueprint $table) {
                $table->unsignedBigInteger('admin_id')->nullable(false)->change();
            });
        }
    }
};
