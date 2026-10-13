<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        if (! Schema::hasColumn('admins', 'deleted_at')) {
            Schema::table('admins', function ($table): void {
                $table->softDeletes();
            });
        }

        if (! Schema::hasColumn('admins', 'status_verifikasi')) {
            return;
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `admins` MODIFY `status_verifikasi` VARCHAR(30) NOT NULL DEFAULT 'pending'");
        }

        DB::transaction(function (): void {
            DB::table('admins')
                ->whereNull('status_verifikasi')
                ->orWhere('status_verifikasi', '')
                ->update(['status_verifikasi' => 'pending']);

            foreach ($this->statusMappings() as $officialStatus => $legacyStatuses) {
                DB::table('admins')
                    ->whereIn('status_verifikasi', $legacyStatuses)
                    ->update(['status_verifikasi' => $officialStatus]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('admins') || ! Schema::hasColumn('admins', 'status_verifikasi')) {
            return;
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `admins` MODIFY `status_verifikasi` VARCHAR(30) NOT NULL DEFAULT 'pending'");
        }
    }

    /**
     * @return array<string,array<int,string>>
     */
    private function statusMappings(): array
    {
        return [
            'pending' => ['menunggu', 'waiting'],
            'active' => ['aktif', 'approved', 'verified'],
            'rejected' => ['ditolak', 'revisi'],
            'inactive' => ['nonaktif'],
        ];
    }
};
