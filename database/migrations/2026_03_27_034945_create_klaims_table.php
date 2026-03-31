<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('klaims', function (Blueprint $table) {
        $table->id();
        $table->foreignId('laporan_hilang_id')->constrained('laporan_barang_hilangs')->onDelete('cascade');
        $table->foreignId('barang_id')->constrained('barangs')->onDelete('cascade');
        $table->foreignId('user_id')->constrained('users');
        $table->foreignId('admin_id')->constrained('admins');
        $table->enum('status_klaim', ['pending', 'disetujui', 'ditolak'])->default('pending');
        $table->text('catatan')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('klaims');
    }
};
