<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pencocokan extends Model
{
    protected $fillable = [
        'laporan_hilang_id',
        'barang_id',
        'admin_id',
        'status_pencocokan',
        'catatan',
        'matched_at',
        // Kolom AI Matching
        'ai_similarity_score',
        'ai_recommendation',
        'ai_reasoning',
        'ai_matched_at',
        'ai_model_used',
    ];

    protected function casts(): array
    {
        return [
            'matched_at'          => 'datetime',
            'ai_matched_at'       => 'datetime',
            'ai_similarity_score' => 'integer',
        ];
    }

    /**
     * Kembalikan label rekomendasi AI dalam Bahasa Indonesia.
     */
    public function aiRecommendationLabel(): string
    {
        return match ($this->ai_recommendation) {
            'high'      => 'Sangat Cocok',
            'medium'    => 'Kemungkinan Cocok',
            'low'       => 'Perlu Verifikasi',
            'uncertain' => 'Kurang Cocok',
            default     => 'Belum Dianalisis',
        };
    }

    /**
     * Kembalikan class CSS untuk badge rekomendasi AI.
     */
    public function aiRecommendationClass(): string
    {
        return match ($this->ai_recommendation) {
            'high'      => 'ai-badge--high',
            'medium'    => 'ai-badge--medium',
            'low'       => 'ai-badge--low',
            'uncertain' => 'ai-badge--uncertain',
            default     => 'ai-badge--none',
        };
    }

    public function laporanHilang()
    {
        return $this->belongsTo(LaporanBarangHilang::class, 'laporan_hilang_id');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function klaims()
    {
        return $this->hasMany(Klaim::class, 'pencocokan_id');
    }
}
