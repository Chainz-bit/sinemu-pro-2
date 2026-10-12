<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('laporan_barang_hilangs') && ! Schema::hasColumn('laporan_barang_hilangs', 'status_laporan')) {
            Schema::table('laporan_barang_hilangs', function (Blueprint $table) {
                $table->string('status_laporan', 30)->default('submitted')->after('tampil_di_home');
                $table->foreignId('verified_by_admin_id')->nullable()->after('status_laporan')->constrained('admins')->nullOnDelete();
                $table->timestamp('verified_at')->nullable()->after('verified_by_admin_id');
                $table->index('status_laporan');
            });
        }

        if (Schema::hasTable('barangs') && ! Schema::hasColumn('barangs', 'status_laporan')) {
            Schema::table('barangs', function (Blueprint $table) {
                $table->string('status_laporan', 30)->default('submitted')->after('tampil_di_home');
                $table->foreignId('verified_by_admin_id')->nullable()->after('status_laporan')->constrained('admins')->nullOnDelete();
                $table->timestamp('verified_at')->nullable()->after('verified_by_admin_id');
                $table->index('status_laporan');
            });
        }

        if (! Schema::hasTable('pencocokans')) {
            Schema::create('pencocokans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('laporan_hilang_id')->constrained('laporan_barang_hilangs')->cascadeOnDelete();
                $table->foreignId('barang_id')->constrained('barangs')->cascadeOnDelete();
                $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
                $table->string('status_pencocokan', 30)->default('confirmed');
                $table->text('catatan')->nullable();
                $table->timestamp('matched_at')->nullable();
                $table->timestamps();

                $table->index('status_pencocokan');
                $table->unique(['laporan_hilang_id', 'barang_id']);
            });
        }

        if (Schema::hasTable('klaims') && ! Schema::hasColumn('klaims', 'pencocokan_id')) {
            Schema::table('klaims', function (Blueprint $table) {
                $table->foreignId('pencocokan_id')->nullable()->after('barang_id')->constrained('pencocokans')->nullOnDelete();
                $table->string('status_verifikasi', 30)->default('submitted')->after('status_klaim');
                $table->index('status_verifikasi');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('klaims') && Schema::hasColumn('klaims', 'pencocokan_id')) {
            Schema::table('klaims', function (Blueprint $table) {
                $table->dropConstrainedForeignId('pencocokan_id');
                $table->dropIndex(['status_verifikasi']);
                $table->dropColumn('status_verifikasi');
            });
        }

        Schema::dropIfExists('pencocokans');

        if (Schema::hasTable('barangs') && Schema::hasColumn('barangs', 'status_laporan')) {
            Schema::table('barangs', function (Blueprint $table) {
                $table->dropConstrainedForeignId('verified_by_admin_id');
                $table->dropIndex(['status_laporan']);
                $table->dropColumn(['status_laporan', 'verified_at']);
            });
        }

        if (Schema::hasTable('laporan_barang_hilangs') && Schema::hasColumn('laporan_barang_hilangs', 'status_laporan')) {
            Schema::table('laporan_barang_hilangs', function (Blueprint $table) {
                $table->dropConstrainedForeignId('verified_by_admin_id');
                $table->dropIndex(['status_laporan']);
                $table->dropColumn(['status_laporan', 'verified_at']);
            });
        }
    }
};
