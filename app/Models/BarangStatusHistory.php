<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarangStatusHistory extends Model
{
    protected $fillable = [
        'barang_id',
        'admin_id',
        'status_lama',
        'status_baru',
        'catatan',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}
