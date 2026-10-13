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
        Schema::table('admins', function (Blueprint $table) {
            if (! Schema::hasColumn('admins', 'email')) {
                $table->string('email')->nullable()->after('nama');
            }
        });

        DB::table('admins')
            ->whereNull('email')
            ->orderBy('id')
            ->lazy()
            ->each(function ($admin) {
                DB::table('admins')
                    ->where('id', $admin->id)
                    ->update(['email' => 'admin'.$admin->id.'@sinemu.local']);
            });

        Schema::table('admins', function (Blueprint $table) {
            $table->unique('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            if (Schema::hasColumn('admins', 'email')) {
                $table->dropUnique(['email']);
                $table->dropColumn('email');
            }
        });
    }
};
