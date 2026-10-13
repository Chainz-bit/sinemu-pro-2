<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username')->unique()->nullable()->after('id');
            }

            if (! Schema::hasColumn('users', 'profil')) {
                $table->text('profil')->nullable()->after('email');
            }

            if (! Schema::hasColumn('users', 'nama')) {
                $table->string('nama')->nullable()->after('name');
            }
        });

        if (Schema::hasColumn('users', 'name') && Schema::hasColumn('users', 'nama')) {
            DB::table('users')
                ->whereNull('nama')
                ->update(['nama' => DB::raw('name')]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'profil')) {
                $table->dropColumn('profil');
            }
            if (Schema::hasColumn('users', 'nama')) {
                $table->dropColumn('nama');
            }
            if (Schema::hasColumn('users', 'username')) {
                $table->dropColumn('username');
            }
        });
    }
};
