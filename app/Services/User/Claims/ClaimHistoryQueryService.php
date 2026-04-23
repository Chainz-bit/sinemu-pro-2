<?php

namespace App\Services\User\Claims;

use App\Models\Klaim;
use App\Support\WorkflowStatus;
use Illuminate\Database\Eloquent\Builder;

class ClaimHistoryQueryService
{
    /**
     * @return Builder<Klaim>
     */
    public function build(int $userId, string $search, string $status, string $type, bool $hasStatusVerifikasi): Builder
    {
        $claimsQuery = Klaim::query()
            ->where('user_id', $userId)
            ->with([
                'barang:id,nama_barang,lokasi_ditemukan,foto_barang,status_barang,lokasi_pengambilan,alamat_pengambilan,penanggung_jawab_pengambilan,kontak_pengambilan,jam_layanan_pengambilan,catatan_pengambilan',
                'laporanHilang:id,nama_barang,lokasi_hilang,foto_barang',
                'admin:id,instansi,kecamatan,alamat_lengkap',
            ])
            ->latest('updated_at');

        $this->applySearch($claimsQuery, $search);
        $this->applyStatus($claimsQuery, $status, $hasStatusVerifikasi);
        $this->applyType($claimsQuery, $type);

        return $claimsQuery;
    }

    /**
     * @param Builder<Klaim> $claimsQuery
     */
    private function applySearch(Builder $claimsQuery, string $search): void
    {
        if ($search === '') {
            return;
        }

        $claimsQuery->where(function ($builder) use ($search) {
            $builder->whereHas('barang', function ($barangQuery) use ($search) {
                $barangQuery
                    ->where('nama_barang', 'like', '%' . $search . '%')
                    ->orWhere('lokasi_ditemukan', 'like', '%' . $search . '%');
            })->orWhereHas('laporanHilang', function ($lostQuery) use ($search) {
                $lostQuery
                    ->where('nama_barang', 'like', '%' . $search . '%')
                    ->orWhere('lokasi_hilang', 'like', '%' . $search . '%');
            });
        });
    }

    /**
     * @param Builder<Klaim> $claimsQuery
     */
    private function applyStatus(Builder $claimsQuery, string $status, bool $hasStatusVerifikasi): void
    {
        if ($status === 'menunggu_tinjauan') {
            if ($hasStatusVerifikasi) {
                $claimsQuery->whereIn('status_verifikasi', [
                    WorkflowStatus::CLAIM_SUBMITTED,
                    WorkflowStatus::CLAIM_UNDER_REVIEW,
                ]);

                return;
            }

            $claimsQuery->where('status_klaim', 'pending');

            return;
        }

        if ($status === 'tidak_disetujui') {
            $hasStatusVerifikasi
                ? $claimsQuery->where('status_verifikasi', WorkflowStatus::CLAIM_REJECTED)
                : $claimsQuery->where('status_klaim', 'ditolak');

            return;
        }

        if ($status === 'sedang_diproses') {
            if ($hasStatusVerifikasi) {
                $claimsQuery->where('status_verifikasi', WorkflowStatus::CLAIM_APPROVED);

                return;
            }

            $claimsQuery->where('status_klaim', 'disetujui')->where(function ($builder) {
                $builder->whereDoesntHave('barang')
                    ->orWhereHas('barang', function ($barangQuery) {
                        $barangQuery->where('status_barang', '!=', 'sudah_dikembalikan');
                    });
            });

            return;
        }

        if ($status === 'selesai') {
            if ($hasStatusVerifikasi) {
                $claimsQuery->where('status_verifikasi', WorkflowStatus::CLAIM_COMPLETED);

                return;
            }

            $claimsQuery->where('status_klaim', 'disetujui')->whereHas('barang', function ($barangQuery) {
                $barangQuery->where('status_barang', 'sudah_dikembalikan');
            });
        }
    }

    /**
     * @param Builder<Klaim> $claimsQuery
     */
    private function applyType(Builder $claimsQuery, string $type): void
    {
        if ($type === 'temuan') {
            $claimsQuery->whereNotNull('barang_id');

            return;
        }

        if ($type === 'hilang') {
            $claimsQuery->whereNotNull('laporan_hilang_id');
        }
    }
}
