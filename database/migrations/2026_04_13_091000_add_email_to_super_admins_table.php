<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('super_admins', function (Blueprint $table) {
            if (!Schema::hasColumn('super_admins', 'email')) {
                $table->string('email')->nullable()->after('nama');
            }
        });

        DB::table('super_admins')
            ->orderBy('id')
            ->lazy()
            ->each(function ($row): void {
                $email = $row->username === 'superadmin'
                    ? 'superadmin@sinemu.com'
                    : ($row->username ?: 'superadmin'.$row->id).'@sinemu.local';

                DB::table('super_admins')
                    ->where('id', $row->id)
                    ->update(['email' => strtolower($email)]);
            });

        Schema::table('super_admins', function (Blueprint $table) {
            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::table('super_admins', function (Blueprint $table) {
            if (Schema::hasColumn('super_admins', 'email')) {
                $table->dropUnique(['email']);
                $table->dropColumn('email');
            }
        });
    }
};
