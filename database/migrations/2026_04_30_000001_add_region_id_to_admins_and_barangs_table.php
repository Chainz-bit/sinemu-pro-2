<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admins') && ! Schema::hasColumn('admins', 'region_id')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->foreignId('region_id')
                    ->nullable()
                    ->after('super_admin_id')
                    ->constrained('wilayahs')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('barangs') && ! Schema::hasColumn('barangs', 'region_id')) {
            Schema::table('barangs', function (Blueprint $table) {
                $table->foreignId('region_id')
                    ->nullable()
                    ->after('admin_id')
                    ->constrained('wilayahs')
                    ->nullOnDelete();

                $table->index(['region_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('barangs') && Schema::hasColumn('barangs', 'region_id')) {
            Schema::table('barangs', function (Blueprint $table) {
                $table->dropIndex(['region_id', 'created_at']);
                $table->dropConstrainedForeignId('region_id');
            });
        }

        if (Schema::hasTable('admins') && Schema::hasColumn('admins', 'region_id')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropConstrainedForeignId('region_id');
            });
        }
    }
};
