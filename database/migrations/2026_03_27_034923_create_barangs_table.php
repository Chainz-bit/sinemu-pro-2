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
    Schema::create('barangs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('admin_id')->constrained('admins')->onDelete('cascade');
        $table->foreignId('kategori_id')->constrained('kategoris');
        $table->string('nama_barang');
        $table->text('deskripsi');
        $table->string('lokasi_ditemukan');
        $table->date('tanggal_ditemukan');
        $table->enum('status_barang', ['tersedia', 'dalam_proses_klaim', 'sudah_diklaim', 'sudah_dikembalikan'])->default('tersedia');
        $table->string('foto_barang')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barangs');
    }
};
