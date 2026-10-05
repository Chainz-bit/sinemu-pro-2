<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pencocokans') && Schema::hasColumn('pencocokans', 'laporan_hilang_id')) {
            Schema::table('pencocokans', function (Blueprint $table) {
                $table->unsignedBigInteger('laporan_hilang_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pencocokans') && Schema::hasColumn('pencocokans', 'laporan_hilang_id')) {
            Schema::table('pencocokans', function (Blueprint $table) {
                $table->unsignedBigInteger('laporan_hilang_id')->nullable(false)->change();
            });
        }
    }
};
