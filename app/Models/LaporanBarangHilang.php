<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaporanBarangHilang extends Model
{
    protected $fillable = ['user_id', 'nama_barang', 'lokasi_hilang', 'tanggal_hilang', 'keterangan', 'foto_barang', 'sumber_laporan'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function klaims()
    {
        return $this->hasMany(Klaim::class, 'laporan_hilang_id');
    }
}
