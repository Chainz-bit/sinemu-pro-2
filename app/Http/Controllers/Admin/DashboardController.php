<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Services\ReportImageCleaner;
use App\Support\Media\OptimizedImageUploader;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function __construct(private readonly OptimizedImageUploader $imageUploader)
    {
    }

    public function index(Request $request)
    {
        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();
        $search = trim((string) $request->query('search', ''));
        $statusFilter = trim((string) $request->query('status', 'semua'));

        $totalHilangQuery = LaporanBarangHilang::query();
        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan')) {
            $totalHilangQuery->where('sumber_laporan', 'lapor_hilang');
        }
        $totalHilang = $totalHilangQuery->count();

        $totalTemuan = Barang::count();

        $menungguVerifikasi = Klaim::where('status_klaim', 'pending')->count();

        $latestReportsCollection = $this->filterLatestReports(
            $this->buildLatestReports(),
            $search,
            $statusFilter
        );

        $latestReports = $this->paginateReports(
            $latestReportsCollection,
            (int) $request->query('page', 1),
            8
        );
        $kategoriOptions = Kategori::query()
            ->orderBy('nama_kategori')
            ->get(['id', 'nama_kategori']);

        return view('admin.pages.dashboard', compact(
            'totalHilang',
            'totalTemuan',
            'menungguVerifikasi',
            'latestReports',
            'kategoriOptions',
            'admin',
            'search',
            'statusFilter'
        ));
    }

    public function updateReport(Request $request, string $type, int $id): RedirectResponse
    {
        abort_if(!Auth::guard('admin')->check(), 403);

        if ($type === 'hilang') {
            $report = LaporanBarangHilang::query()->findOrFail($id);
            if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan') && $report->sumber_laporan !== 'lapor_hilang') {
                abort(404);
            }

            $validated = $request->validate([
                'nama_barang' => ['required', 'string', 'max:255'],
                'lokasi_hilang' => ['required', 'string', 'max:255'],
                'tanggal_hilang' => ['required', 'date'],
                'keterangan' => ['nullable', 'string', 'max:2000'],
                'foto_barang' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            ]);

            $payload = [
                'nama_barang' => $validated['nama_barang'],
                'lokasi_hilang' => $validated['lokasi_hilang'],
                'tanggal_hilang' => $validated['tanggal_hilang'],
                'keterangan' => isset($validated['keterangan']) && trim((string) $validated['keterangan']) !== ''
                    ? trim((string) $validated['keterangan'])
                    : null,
            ];

            $photo = $request->file('foto_barang');
            if ($photo) {
                $oldPhotoPath = $report->foto_barang;
                $payload['foto_barang'] = $this->imageUploader->upload($photo, 'barang-hilang/' . now()->format('Y/m'));
            }

            $report->update($payload);

            if (!empty($oldPhotoPath ?? null)) {
                ReportImageCleaner::purgeIfOrphaned($oldPhotoPath);
            }

            return back()->with('status', 'Laporan barang hilang berhasil diperbarui.');
        }

        if ($type === 'temuan') {
            $report = Barang::query()->findOrFail($id);
            $validated = $request->validate([
                'nama_barang' => ['required', 'string', 'max:255'],
                'kategori_id' => ['nullable', 'integer', 'exists:kategoris,id'],
                'deskripsi' => ['nullable', 'string', 'max:2000'],
                'lokasi_ditemukan' => ['required', 'string', 'max:255'],
                'tanggal_ditemukan' => ['required', 'date'],
                'status_barang' => ['required', 'in:tersedia,dalam_proses_klaim,sudah_diklaim,sudah_dikembalikan'],
                'lokasi_pengambilan' => ['nullable', 'string', 'max:255'],
                'alamat_pengambilan' => ['nullable', 'string', 'max:255'],
                'penanggung_jawab_pengambilan' => ['nullable', 'string', 'max:255'],
                'kontak_pengambilan' => ['nullable', 'string', 'max:255'],
                'jam_layanan_pengambilan' => ['nullable', 'string', 'max:255'],
                'catatan_pengambilan' => ['nullable', 'string', 'max:2000'],
                'foto_barang' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            ]);

            $payload = [
                'nama_barang' => $validated['nama_barang'],
                'kategori_id' => $validated['kategori_id'] ?? $report->kategori_id,
                'deskripsi' => isset($validated['deskripsi']) && trim((string) $validated['deskripsi']) !== ''
                    ? trim((string) $validated['deskripsi'])
                    : ((string) ($report->deskripsi ?? '')),
                'lokasi_ditemukan' => $validated['lokasi_ditemukan'],
                'tanggal_ditemukan' => $validated['tanggal_ditemukan'],
                'status_barang' => $validated['status_barang'],
                'lokasi_pengambilan' => isset($validated['lokasi_pengambilan']) && trim((string) $validated['lokasi_pengambilan']) !== ''
                    ? trim((string) $validated['lokasi_pengambilan'])
                    : null,
                'alamat_pengambilan' => isset($validated['alamat_pengambilan']) && trim((string) $validated['alamat_pengambilan']) !== ''
                    ? trim((string) $validated['alamat_pengambilan'])
                    : null,
                'penanggung_jawab_pengambilan' => isset($validated['penanggung_jawab_pengambilan']) && trim((string) $validated['penanggung_jawab_pengambilan']) !== ''
                    ? trim((string) $validated['penanggung_jawab_pengambilan'])
                    : null,
                'kontak_pengambilan' => isset($validated['kontak_pengambilan']) && trim((string) $validated['kontak_pengambilan']) !== ''
                    ? trim((string) $validated['kontak_pengambilan'])
                    : null,
                'jam_layanan_pengambilan' => isset($validated['jam_layanan_pengambilan']) && trim((string) $validated['jam_layanan_pengambilan']) !== ''
                    ? trim((string) $validated['jam_layanan_pengambilan'])
                    : null,
                'catatan_pengambilan' => isset($validated['catatan_pengambilan']) && trim((string) $validated['catatan_pengambilan']) !== ''
                    ? trim((string) $validated['catatan_pengambilan'])
                    : null,
            ];

            $photo = $request->file('foto_barang');
            if ($photo) {
                $oldPhotoPath = $report->foto_barang;
                $payload['foto_barang'] = $this->imageUploader->upload($photo, 'barang-temuan/' . now()->format('Y/m'));
            }

            $report->update($payload);

            if (!empty($oldPhotoPath ?? null)) {
                ReportImageCleaner::purgeIfOrphaned($oldPhotoPath);
            }

            return back()->with('status', 'Laporan barang temuan berhasil diperbarui.');
        }

        if ($type === 'klaim') {
            $report = Klaim::query()->with('barang')->findOrFail($id);
            $validated = $request->validate([
                'status_klaim' => ['required', 'in:pending,disetujui,ditolak'],
                'catatan' => ['nullable', 'string', 'max:2000'],
            ]);

            $oldStatus = (string) $report->status_klaim;
            $newStatus = (string) $validated['status_klaim'];

            $report->update([
                'status_klaim' => $newStatus,
                'catatan' => $validated['catatan'] ?? null,
                'admin_id' => (int) Auth::guard('admin')->id(),
            ]);

            if ($report->barang && $oldStatus !== $newStatus) {
                if ($newStatus === 'disetujui') {
                    $report->barang->update(['status_barang' => 'sudah_diklaim']);
                } elseif ($newStatus === 'ditolak' && $report->barang->status_barang === 'dalam_proses_klaim') {
                    $report->barang->update(['status_barang' => 'tersedia']);
                }
            }

            return back()->with('status', 'Data klaim berhasil diperbarui.');
        }

        abort(404);
    }

    public function publishToHome(Request $request, string $type, int $id): RedirectResponse
    {
        abort_if(!Auth::guard('admin')->check(), 403);

        if ($type === 'hilang') {
            $report = LaporanBarangHilang::query()->findOrFail($id);
            if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan') && $report->sumber_laporan !== 'lapor_hilang') {
                abort(404);
            }

            if (!Schema::hasColumn('laporan_barang_hilangs', 'tampil_di_home')) {
                return back()->with('error', 'Kolom tampil_di_home belum tersedia pada laporan barang hilang.');
            }

            $report->update(['tampil_di_home' => true]);

            return back()->with('status', 'Laporan barang hilang berhasil diupload ke Home.');
        }

        if ($type === 'temuan') {
            $report = Barang::query()->findOrFail($id);
            if (!Schema::hasColumn('barangs', 'tampil_di_home')) {
                return back()->with('error', 'Kolom tampil_di_home belum tersedia pada barang temuan.');
            }

            $report->update(['tampil_di_home' => true]);

            return back()->with('status', 'Laporan barang temuan berhasil diupload ke Home.');
        }

        if ($type === 'klaim') {
            $claim = Klaim::query()->findOrFail($id);

            if (!is_null($claim->barang_id)) {
                $report = Barang::query()->find($claim->barang_id);
                if (!$report) {
                    return back()->with('error', 'Data barang temuan terkait klaim tidak ditemukan.');
                }

                if (!Schema::hasColumn('barangs', 'tampil_di_home')) {
                    return back()->with('error', 'Kolom tampil_di_home belum tersedia pada barang temuan.');
                }

                $report->update(['tampil_di_home' => true]);

                return back()->with('status', 'Barang temuan dari klaim berhasil diupload ke Home.');
            }

            if (!is_null($claim->laporan_hilang_id)) {
                $report = LaporanBarangHilang::query()->find($claim->laporan_hilang_id);
                if (!$report) {
                    return back()->with('error', 'Data barang hilang terkait klaim tidak ditemukan.');
                }

                if (!Schema::hasColumn('laporan_barang_hilangs', 'tampil_di_home')) {
                    return back()->with('error', 'Kolom tampil_di_home belum tersedia pada laporan barang hilang.');
                }

                $report->update(['tampil_di_home' => true]);

                return back()->with('status', 'Barang hilang dari klaim berhasil diupload ke Home.');
            }
        }

        abort(404);
    }

    private function buildLatestReports(): Collection
    {
        $lostHasHomeFlag = Schema::hasColumn('laporan_barang_hilangs', 'tampil_di_home');
        $foundHasHomeFlag = Schema::hasColumn('barangs', 'tampil_di_home');

        $lostSelectColumns = ['id', 'nama_barang', 'lokasi_hilang', 'tanggal_hilang', 'keterangan', 'foto_barang', 'created_at', 'updated_at'];
        if ($lostHasHomeFlag) {
            $lostSelectColumns[] = 'tampil_di_home';
        }

        $lostReportsQuery = LaporanBarangHilang::query()
            ->select($lostSelectColumns)
            ->with('user:id,name,nama')
            ->selectSub(
                Klaim::query()
                    ->whereColumn('laporan_hilang_id', 'laporan_barang_hilangs.id')
                    ->orderByDesc('updated_at')
                    ->limit(1)
                    ->select('status_klaim'),
                'latest_claim_status'
            )
            ->selectSub(
                Klaim::query()
                    ->whereColumn('laporan_hilang_id', 'laporan_barang_hilangs.id')
                    ->orderByDesc('updated_at')
                    ->limit(1)
                    ->select('updated_at'),
                'latest_claim_activity_at'
            );

        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan')) {
            $lostReportsQuery->where('sumber_laporan', 'lapor_hilang');
        }

        $lostReports = $lostReportsQuery
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(function ($report) {
                $status = match ($report->latest_claim_status) {
                    'disetujui' => 'selesai',
                    'ditolak' => 'ditolak',
                    'pending' => 'dalam_peninjauan',
                    default => 'diproses',
                };

                $pelapor = $report->user?->nama ?? $report->user?->name ?? 'Pengguna';

                $activityAt = max(
                    strtotime((string) $report->updated_at),
                    strtotime((string) ($report->latest_claim_activity_at ?? $report->created_at))
                );

                return (object) [
                    'id' => (int) $report->id,
                    'type' => 'hilang',
                    'item_name' => $report->nama_barang,
                    'item_detail' => 'Pelapor: ' . $pelapor . ' - Layanan: Barang Hilang - ' . $report->lokasi_hilang,
                    'incident_date' => $report->tanggal_hilang,
                    'created_at' => $report->created_at,
                    'activity_at' => $activityAt,
                    'status' => $status,
                    'status_label' => 'Laporan Hilang',
                    'avatar' => 'H',
                    'avatar_class' => 'avatar-sand',
                    'foto_barang' => $report->foto_barang,
                    'detail_url' => route('admin.lost-items.show', $report->id),
                    'edit_url' => route('admin.lost-items.show', $report->id),
                    'edit_nama_barang' => $report->nama_barang,
                    'edit_lokasi_hilang' => $report->lokasi_hilang,
                    'edit_tanggal_hilang' => $report->tanggal_hilang,
                    'edit_keterangan' => $report->keterangan,
                    'update_url' => route('admin.dashboard.reports.update', ['type' => 'hilang', 'id' => $report->id]),
                    'upload_home_url' => route('admin.dashboard.reports.publish-home', ['type' => 'hilang', 'id' => $report->id]),
                    'home_published' => (bool) ($report->tampil_di_home ?? false),
                    'target_url' => route('admin.lost-items', ['search' => $report->nama_barang]),
                    'target_label' => 'Buka Barang Hilang',
                    'delete_url' => route('admin.lost-items.destroy', $report->id),
                ];
            });

        $foundSelectColumns = [
            'id',
            'nama_barang',
            'kategori_id',
            'deskripsi',
            'lokasi_ditemukan',
            'tanggal_ditemukan',
            'status_barang',
            'lokasi_pengambilan',
            'alamat_pengambilan',
            'penanggung_jawab_pengambilan',
            'kontak_pengambilan',
            'jam_layanan_pengambilan',
            'catatan_pengambilan',
            'foto_barang',
            'created_at',
            'updated_at',
        ];
        if ($foundHasHomeFlag) {
            $foundSelectColumns[] = 'tampil_di_home';
        }

        $foundReports = Barang::query()
            ->with('admin:id,nama')
            ->select($foundSelectColumns)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(function ($report) {
                $status = match ($report->status_barang) {
                    'dalam_proses_klaim' => 'dalam_peninjauan',
                    'sudah_diklaim', 'sudah_dikembalikan' => 'selesai',
                    default => 'diproses',
                };

                $pelapor = $report->admin?->nama ?? 'Admin';

                $activityAt = strtotime((string) ($report->updated_at ?? $report->created_at));

                return (object) [
                    'id' => (int) $report->id,
                    'type' => 'temuan',
                    'item_name' => $report->nama_barang,
                    'item_detail' => 'Pelapor: ' . $pelapor . ' - Layanan: Barang Temuan - ' . $report->lokasi_ditemukan,
                    'incident_date' => $report->tanggal_ditemukan,
                    'created_at' => $report->created_at,
                    'activity_at' => $activityAt,
                    'status' => $status,
                    'status_label' => 'Barang Temuan',
                    'avatar' => 'T',
                    'avatar_class' => 'avatar-mint',
                    'foto_barang' => $report->foto_barang,
                    'detail_url' => route('admin.found-items.show', $report->id),
                    'edit_url' => route('admin.found-items.show', $report->id),
                    'edit_nama_barang' => $report->nama_barang,
                    'edit_kategori_id' => $report->kategori_id,
                    'edit_deskripsi' => $report->deskripsi,
                    'edit_lokasi_ditemukan' => $report->lokasi_ditemukan,
                    'edit_tanggal_ditemukan' => $report->tanggal_ditemukan,
                    'edit_status_barang' => $report->status_barang,
                    'edit_lokasi_pengambilan' => $report->lokasi_pengambilan,
                    'edit_alamat_pengambilan' => $report->alamat_pengambilan,
                    'edit_penanggung_jawab_pengambilan' => $report->penanggung_jawab_pengambilan,
                    'edit_kontak_pengambilan' => $report->kontak_pengambilan,
                    'edit_jam_layanan_pengambilan' => $report->jam_layanan_pengambilan,
                    'edit_catatan_pengambilan' => $report->catatan_pengambilan,
                    'update_url' => route('admin.dashboard.reports.update', ['type' => 'temuan', 'id' => $report->id]),
                    'upload_home_url' => route('admin.dashboard.reports.publish-home', ['type' => 'temuan', 'id' => $report->id]),
                    'home_published' => (bool) ($report->tampil_di_home ?? false),
                    'target_url' => route('admin.found-items.show', $report->id),
                    'target_label' => 'Buka Barang Temuan',
                    'delete_url' => route('admin.found-items.destroy', $report->id),
                ];
            });

        $claimReports = Klaim::query()
            ->with([
                'barang' => function ($query) use ($foundHasHomeFlag) {
                    $columns = ['id', 'nama_barang', 'lokasi_ditemukan', 'foto_barang'];
                    if ($foundHasHomeFlag) {
                        $columns[] = 'tampil_di_home';
                    }
                    $query->select($columns);
                },
                'laporanHilang' => function ($query) use ($lostHasHomeFlag) {
                    $columns = ['id', 'nama_barang', 'lokasi_hilang', 'foto_barang'];
                    if ($lostHasHomeFlag) {
                        $columns[] = 'tampil_di_home';
                    }
                    $query->select($columns);
                },
                'user:id,nama,name',
            ])
            ->select('id', 'status_klaim', 'catatan', 'created_at', 'updated_at', 'barang_id', 'laporan_hilang_id')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(function ($claim) {
                $status = match ($claim->status_klaim) {
                    'disetujui' => 'selesai',
                    'ditolak' => 'ditolak',
                    default => 'dalam_peninjauan',
                };

                $namaBarang = $claim->barang?->nama_barang
                    ?? $claim->laporanHilang?->nama_barang
                    ?? 'Klaim Barang';

                $detailUrl = match (true) {
                    !is_null($claim->barang_id) => route('admin.found-items.show', $claim->barang_id),
                    !is_null($claim->laporan_hilang_id) => route('admin.lost-items.show', $claim->laporan_hilang_id),
                    default => route('admin.claim-verifications.show', $claim->id),
                };
                $uploadHomeUrl = match (true) {
                    !is_null($claim->barang_id) => route('admin.dashboard.reports.publish-home', ['type' => 'temuan', 'id' => $claim->barang_id]),
                    !is_null($claim->laporan_hilang_id) => route('admin.dashboard.reports.publish-home', ['type' => 'hilang', 'id' => $claim->laporan_hilang_id]),
                    default => null,
                };
                $homePublished = match (true) {
                    !is_null($claim->barang?->tampil_di_home ?? null) => (bool) $claim->barang?->tampil_di_home,
                    !is_null($claim->laporanHilang?->tampil_di_home ?? null) => (bool) $claim->laporanHilang?->tampil_di_home,
                    default => false,
                };

                $lokasi = $claim->barang?->lokasi_ditemukan
                    ?? $claim->laporanHilang?->lokasi_hilang
                    ?? 'Lokasi tidak tersedia';
                $pelapor = $claim->user?->nama ?? $claim->user?->name ?? 'Pengguna';

                $activityAt = strtotime((string) ($claim->updated_at ?? $claim->created_at));

                return (object) [
                    'id' => (int) $claim->id,
                    'type' => 'klaim',
                    'item_name' => $namaBarang,
                    'item_detail' => 'Pelapor: ' . $pelapor . ' - Layanan: Verifikasi Klaim - ' . $lokasi,
                    'incident_date' => $claim->created_at,
                    'created_at' => $claim->created_at,
                    'activity_at' => $activityAt,
                    'status' => $status,
                    'status_label' => 'Verifikasi Klaim',
                    'avatar' => 'K',
                    'avatar_class' => 'avatar-claim',
                    'foto_barang' => $claim->barang?->foto_barang ?? $claim->laporanHilang?->foto_barang,
                    'detail_url' => $detailUrl,
                    'edit_url' => route('admin.claim-verifications.show', $claim->id),
                    'edit_status_klaim' => $claim->status_klaim,
                    'edit_catatan' => $claim->catatan,
                    'update_url' => route('admin.dashboard.reports.update', ['type' => 'klaim', 'id' => $claim->id]),
                    'upload_home_url' => $uploadHomeUrl,
                    'home_published' => $homePublished,
                    'target_url' => route('admin.claim-verifications', [
                        'search' => $namaBarang,
                        'status' => $claim->status_klaim,
                    ]),
                    'target_label' => 'Buka Verifikasi Klaim',
                    'delete_url' => route('admin.claim-verifications.destroy', $claim->id),
                ];
            });

        return $lostReports
            ->merge($foundReports)
            ->merge($claimReports)
            ->sortByDesc('activity_at')
            ->take(10)
            ->values();
    }

    private function paginateReports(Collection $items, int $page, int $perPage): LengthAwarePaginator
    {
        $page = max($page, 1);
        $total = $items->count();
        $currentPageItems = $items->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $currentPageItems,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    private function filterLatestReports(Collection $items, string $search, string $statusFilter): Collection
    {
        if ($search !== '') {
            $keyword = mb_strtolower($search);
            $items = $items->filter(function ($item) use ($keyword) {
                $haystack = mb_strtolower(
                    trim(
                        implode(' ', [
                            (string) ($item->item_name ?? ''),
                            (string) ($item->item_detail ?? ''),
                            (string) ($item->status ?? ''),
                            (string) ($item->status_label ?? ''),
                        ])
                    )
                );

                return str_contains($haystack, $keyword);
            });
        }

        if ($statusFilter !== '' && $statusFilter !== 'semua') {
            $items = $items->filter(function ($item) use ($statusFilter) {
                return (string) ($item->status ?? '') === $statusFilter;
            });
        }

        return $items->values();
    }
}
