<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Klaim extends Model
{
    protected $fillable = ['laporan_hilang_id', 'barang_id', 'user_id', 'admin_id', 'status_klaim', 'catatan'];

    public function laporanHilang()
    {
        return $this->belongsTo(LaporanBarangHilang::class, 'laporan_hilang_id');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}