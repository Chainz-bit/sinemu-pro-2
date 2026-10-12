<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $admin_id
 * @property string $type
 * @property string $title
 * @property string $message
 * @property string|null $action_url
 * @property array|null $meta
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AdminNotification extends Model
{
    protected $fillable = [
        'admin_id',
        'type',
        'title',
        'message',
        'action_url',
        'meta',
        'read_at',
        'created_at', // ← DITAMBAHKAN
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}
