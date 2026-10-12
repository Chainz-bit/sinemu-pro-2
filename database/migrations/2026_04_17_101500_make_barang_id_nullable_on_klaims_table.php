<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // skip kalau SQLite
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('klaims', function (Blueprint $table) {
            $table->unsignedBigInteger('barang_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('klaims', function (Blueprint $table) {
            $table->unsignedBigInteger('barang_id')->nullable(false)->change();
        });
    }
};
