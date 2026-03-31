<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    protected $fillable = ['admin_id', 'kategori_id', 'nama_barang', 'deskripsi', 'lokasi_ditemukan', 'tanggal_ditemukan', 'status_barang', 'foto_barang'];

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
}