<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add AI matching columns to pencocokans table.
 *
 * Columns added (non-destructive – all nullable to protect existing rows):
 *   - ai_similarity_score   : float 0–100, skor kemiripan dari Gemini
 *   - ai_recommendation     : enum, rekomendasi AI (high / medium / low / uncertain)
 *   - ai_reasoning          : text, penjelasan singkat dari AI
 *   - ai_matched_at         : timestamp, kapan AI terakhir kali menjalankan pencocokan
 *   - ai_model_used         : string, nama model Gemini yang dipakai (untuk audit)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pencocokans')) {
            return; // tabel belum ada, lewati (seharusnya tidak terjadi)
        }

        Schema::table('pencocokans', function (Blueprint $table) {
            if (!Schema::hasColumn('pencocokans', 'ai_similarity_score')) {
                $table->unsignedTinyInteger('ai_similarity_score')
                    ->nullable()
                    ->after('catatan')
                    ->comment('Skor kemiripan AI (0–100)');
            }

            if (!Schema::hasColumn('pencocokans', 'ai_recommendation')) {
                $table->string('ai_recommendation', 20)
                    ->nullable()
                    ->after('ai_similarity_score')
                    ->comment('Rekomendasi AI: high | medium | low | uncertain');
            }

            if (!Schema::hasColumn('pencocokans', 'ai_reasoning')) {
                $table->text('ai_reasoning')
                    ->nullable()
                    ->after('ai_recommendation')
                    ->comment('Penjelasan singkat dari AI');
            }

            if (!Schema::hasColumn('pencocokans', 'ai_matched_at')) {
                $table->timestamp('ai_matched_at')
                    ->nullable()
                    ->after('ai_reasoning')
                    ->comment('Waktu terakhir AI menjalankan pencocokan');
            }

            if (!Schema::hasColumn('pencocokans', 'ai_model_used')) {
                $table->string('ai_model_used', 60)
                    ->nullable()
                    ->after('ai_matched_at')
                    ->comment('Nama model Gemini yang digunakan');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('pencocokans')) {
            return;
        }

        Schema::table('pencocokans', function (Blueprint $table) {
            $columns = ['ai_similarity_score', 'ai_recommendation', 'ai_reasoning', 'ai_matched_at', 'ai_model_used'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('pencocokans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
