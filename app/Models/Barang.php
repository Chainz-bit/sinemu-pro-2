<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    protected $fillable = [
        'admin_id',
        'kategori_id',
        'nama_barang',
        'deskripsi',
        'lokasi_ditemukan',
        'tanggal_ditemukan',
        'status_barang',
        'foto_barang',
        'tampil_di_home',
        'lokasi_pengambilan',
        'alamat_pengambilan',
        'penanggung_jawab_pengambilan',
        'kontak_pengambilan',
        'jam_layanan_pengambilan',
        'catatan_pengambilan',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function kategori()
    {
        return $this->belongsTo(Kategori::class);
    }

    public function klaims()
    {
        return $this->hasMany(Klaim::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(BarangStatusHistory::class)->latest();
    }
}
