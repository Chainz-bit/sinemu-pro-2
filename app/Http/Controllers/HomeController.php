<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\LaporanBarangHilang;
use App\Models\Wilayah;
use App\Support\ReportStatusPresenter;
use App\Support\WorkflowStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Throwable;

class HomeController extends Controller
{
    private const HOME_ITEMS_LIMIT = 24;
    private bool $skipDatabaseCalls = false;
    private bool $databaseFailureReported = false;
    private bool $databaseReachabilityChecked = false;

    public function index(): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        [$lostItems, $lostTotalCount] = $this->getLostItems();
        [$foundItems, $foundTotalCount] = $this->getFoundItems();
        [$categories, $kategoriOptions] = $this->getCategories($foundItems);
        [$regions, $mapRegions] = $this->getRegions($lostItems, $foundItems);
        $pickupLocations = $this->getPickupLocations();
        $currentUser = Auth::user();
        $userName = $this->resolveUserDisplayName($currentUser);
        $userAvatar = $this->resolveUserAvatarUrl($currentUser?->profil ?? null);
        $userLocation = $currentUser?->location ?? 'Lokasi Anda';
        $claimableLostReports = Auth::check() ? $this->getClaimableLostReports() : collect();

        return view('home', compact(
            'lostItems',
            'foundItems',
            'lostTotalCount',
            'foundTotalCount',
            'categories',
            'kategoriOptions',
            'regions',
            'mapRegions',
            'pickupLocations',
            'userName',
            'userAvatar',
            'userLocation',
            'claimableLostReports'
        ));
    }

    private function getLostItems(): array
    {
        $lostItems = [];
        $lostTotalCount = 0;
        if ($this->hasDatabaseTable('laporan_barang_hilangs')) {
            [$lostItems, $lostTotalCount] = $this->safeDatabaseCall(function () {
                $lostQuery = LaporanBarangHilang::query();
                $lostQuery = $this->resolveHomeScopeQuery($lostQuery, 'laporan_barang_hilangs');
                $lostTotalCount = (clone $lostQuery)->count();

                $lostItemsQuery = $lostQuery
                    ->select([
                        'id',
                        'kategori_barang',
                        'nama_barang',
                        'lokasi_hilang',
                        'tanggal_hilang',
                        'foto_barang',
                        'status_laporan',
                        'updated_at',
                    ])
                    ->latest('updated_at');

                $lostItemsQuery->limit(self::HOME_ITEMS_LIMIT);

                $lostItems = $lostItemsQuery
                    ->get()
                    ->map(function ($item) {
                        $categoryLabel = trim((string) ($item->kategori_barang ?? ''));
                        $reportStatus = ReportStatusPresenter::key((string) ($item->status_laporan ?? WorkflowStatus::REPORT_SUBMITTED));
                        [$lostStatusLabel, $lostStatusClass] = match ($reportStatus) {
                            WorkflowStatus::REPORT_APPROVED => ['Terverifikasi', 'item-status-info'],
                            WorkflowStatus::REPORT_MATCHED => ['Sudah Dicocokkan', 'item-status-success'],
                            WorkflowStatus::REPORT_CLAIMED => ['Sedang Diklaim', 'item-status-warning'],
                            WorkflowStatus::REPORT_COMPLETED => ['Selesai', 'item-status-success'],
                            WorkflowStatus::REPORT_REJECTED => ['Ditolak', 'item-status-muted'],
                            default => ['Menunggu Verifikasi', 'item-status-warning'],
                        };

                        return [
                            'id' => $item->id,
                            'category' => strtoupper($categoryLabel !== '' ? $categoryLabel : 'UMUM'),
                            'name' => $item->nama_barang,
                            'location' => $this->normalizeLocationLabel((string) $item->lokasi_hilang),
                            'date' => $item->tanggal_hilang ? Carbon::parse((string) $item->tanggal_hilang)->format('m/d/Y') : '',
                            'date_label' => $item->tanggal_hilang ? Carbon::parse((string) $item->tanggal_hilang)->translatedFormat('d M Y') : '-',
                            'image_url' => $this->resolveItemImageUrl((string) ($item->foto_barang ?? ''), 'barang-hilang'),
                            'detail_url' => route('home.lost-detail', $item->id),
                            'status_label' => $lostStatusLabel,
                            'status_class' => $lostStatusClass,
                        ];
                    })
                    ->values()
                    ->all();

                return [$lostItems, $lostTotalCount];
            }, [[], 0]);
        }
        return [$lostItems, $lostTotalCount];
    }

    private function getFoundItems(): array
    {
        $foundItems = [];
        $foundTotalCount = 0;
        if ($this->hasDatabaseTable('barangs')) {
            [$foundItems, $foundTotalCount] = $this->safeDatabaseCall(function () {
                $foundQuery = Barang::query()
                    ->with('kategori:id,nama_kategori');
                $foundQuery = $this->resolveHomeScopeQuery($foundQuery, 'barangs');
                $foundTotalCount = (clone $foundQuery)->count();

                $foundItemsQuery = $foundQuery
                    ->select([
                        'id',
                        'kategori_id',
                        'nama_barang',
                        'lokasi_ditemukan',
                        'tanggal_ditemukan',
                        'status_barang',
                        'foto_barang',
                        'updated_at',
                    ])
                    ->latest('updated_at');

                $foundItemsQuery->limit(self::HOME_ITEMS_LIMIT);

                $foundItems = $foundItemsQuery
                    ->get()
                    ->map(function ($item) {
                        $statusBarang = (string) ($item->status_barang ?? '');
                        $claimStatusKey = match ($statusBarang) {
                            'dalam_proses_klaim' => 'in_progress',
                            'sudah_diklaim' => 'claimed',
                            'sudah_dikembalikan' => 'returned',
                            default => 'available',
                        };

                        return [
                            'id' => $item->id,
                            'category' => strtoupper($item->kategori->nama_kategori ?? 'UMUM'),
                            'name' => $item->nama_barang,
                            'location' => $this->normalizeLocationLabel((string) $item->lokasi_ditemukan),
                            'date' => $item->tanggal_ditemukan ? Carbon::parse((string) $item->tanggal_ditemukan)->format('m/d/Y') : '',
                            'date_label' => $item->tanggal_ditemukan ? Carbon::parse((string) $item->tanggal_ditemukan)->translatedFormat('d M Y') : '-',
                            'image_url' => $this->resolveItemImageUrl((string) ($item->foto_barang ?? ''), 'barang-temuan'),
                            'detail_url' => route('home.found-detail', $item->id),
                            'claim_status_key' => $claimStatusKey,
                            'claim_status_label' => match ($claimStatusKey) {
                                'in_progress' => 'Sedang Diproses Klaim',
                                'claimed' => 'Sudah Diklaim',
                                'returned' => 'Sudah Dikembalikan',
                                default => 'Tersedia untuk Diklaim',
                            },
                            'is_claimable' => $claimStatusKey === 'available',
                        ];
                    })
                    ->values()
                    ->all();

                return [$foundItems, $foundTotalCount];
            }, [[], 0]);
        }
        return [$foundItems, $foundTotalCount];
    }

    private function getCategories(array $foundItems): array
    {
        $categories = ['Semua Kategori'];
        $kategoriOptions = collect();
        if ($this->hasDatabaseTable('kategoris')) {
            $kategoriOptions = $this->safeDatabaseCall(
                fn () => Kategori::query()
                    ->forForm()
                    ->get(['id', 'nama_kategori']),
                collect()
            );
        }

        // Fallback kategori dari data item jika master kategori kosong/tidak lengkap.
        $categoryLabels = $kategoriOptions
            ->pluck('nama_kategori')
            ->merge(
                collect($foundItems)
                    ->pluck('category')
                    ->map(fn ($label) => ucwords(strtolower((string) $label)))
            )
            ->map(fn ($label) => trim((string) $label))
            ->filter(fn ($label) => $label !== '')
            ->reject(fn ($label) => Str::lower($label) === 'tas')
            ->unique(fn ($label) => Str::lower($label))
            ->sortBy(function ($label) {
                $lower = Str::lower($label);
                return $lower === 'lainnya' ? 'zzzzzz' : $lower;
            })
            ->values()
            ->all();

        if (count($categoryLabels) > 0) {
            $categories = array_merge(['Semua Kategori'], $categoryLabels);
        }
        return [$categories, $kategoriOptions];
    }

    private function getRegions(array $lostItems, array $foundItems): array
    {
        $regions = ['Seluruh Wilayah'];
        $mapRegions = [];
        if ($this->hasDatabaseTable('wilayahs')) {
            [$regions, $mapRegions] = $this->safeDatabaseCall(function () use ($lostItems, $foundItems) {
                $regions = ['Seluruh Wilayah'];
                $mapRegions = [];

                $wilayahs = Wilayah::query()
                    ->orderBy('nama_wilayah')
                    ->get(['nama_wilayah', 'lat', 'lng']);

                $regionLabels = $wilayahs
                    ->pluck('nama_wilayah')
                    ->map(fn ($label) => trim((string) $label))
                    ->filter(fn ($label) => $label !== '')
                    ->unique(fn ($label) => Str::lower($label))
                    ->values()
                    ->all();

                if (count($regionLabels) > 0) {
                    $regions = array_merge(['Seluruh Wilayah'], $regionLabels);
                }

                $allLocations = collect(array_merge(
                    array_column($lostItems, 'location'),
                    array_column($foundItems, 'location')
                ))->map(fn ($loc) => Str::lower((string) $loc));

                $mapRegions = $wilayahs->map(function ($wilayah) use ($allLocations) {
                    $key = Str::lower(str_replace('kecamatan', '', $wilayah->nama_wilayah));
                    $activePoints = $allLocations->filter(function ($loc) use ($key) {
                        return str_contains($loc, trim($key));
                    })->count();

                    return [
                        'name' => $wilayah->nama_wilayah,
                        'slug' => Str::slug($wilayah->nama_wilayah),
                        'lat' => $wilayah->lat ? (float) $wilayah->lat : null,
                        'lng' => $wilayah->lng ? (float) $wilayah->lng : null,
                        'active_points' => $activePoints,
                    ];
                })->values()->all();

                return [$regions, $mapRegions];
            }, [$regions, []]);
        }

        // Fallback wilayah dari lokasi item saat tabel wilayah kosong.
        if (count($regions) === 1) {
            $regionFromItems = collect(array_merge(
                array_column($lostItems, 'location'),
                array_column($foundItems, 'location')
            ))
                ->map(fn ($label) => trim((string) $label))
                ->filter(fn ($label) => $label !== '')
                ->unique(fn ($label) => Str::lower($label))
                ->sortBy(fn ($label) => Str::lower($label))
                ->values()
                ->all();

            if (count($regionFromItems) > 0) {
                $regions = array_merge(['Seluruh Wilayah'], $regionFromItems);
            }
        }
        return [$regions, $mapRegions];
    }

    private function getPickupLocations(): array
    {
        $pickupLocations = [];
        if ($this->hasDatabaseTable('admins')) {
            $pickupLocations = $this->safeDatabaseCall(function () {
                $hasStatusVerifikasi = $this->hasDatabaseColumn('admins', 'status_verifikasi');
                $hasKecamatan = $this->hasDatabaseColumn('admins', 'kecamatan');
                $hasAlamatLengkap = $this->hasDatabaseColumn('admins', 'alamat_lengkap');
                $hasLat = $this->hasDatabaseColumn('admins', 'lat');
                $hasLng = $this->hasDatabaseColumn('admins', 'lng');

                $selectColumns = ['id', 'instansi'];
                if ($hasKecamatan) {
                    $selectColumns[] = 'kecamatan';
                }
                if ($hasAlamatLengkap) {
                    $selectColumns[] = 'alamat_lengkap';
                }
                if ($hasLat) {
                    $selectColumns[] = 'lat';
                }
                if ($hasLng) {
                    $selectColumns[] = 'lng';
                }

                $pickupQuery = Admin::query()->orderBy('instansi');

                if ($hasStatusVerifikasi) {
                    $pickupQuery->where('status_verifikasi', 'active');
                }

                return $pickupQuery
                    ->get($selectColumns)
                    ->map(function (Admin $admin) use ($hasKecamatan, $hasAlamatLengkap, $hasLat, $hasLng) {
                        $instansi = trim((string) ($admin->instansi ?? ''));
                        $kecamatan = $hasKecamatan ? trim((string) ($admin->kecamatan ?? '')) : '';
                        $alamatLengkap = $hasAlamatLengkap ? trim((string) ($admin->alamat_lengkap ?? '')) : '';
                        $address = $alamatLengkap !== ''
                            ? $alamatLengkap
                            : ($kecamatan !== '' ? ('Kecamatan ' . $kecamatan) : ($instansi !== '' ? $instansi : 'Alamat belum tersedia'));

                        return [
                            'id' => $admin->id,
                            'name' => $instansi !== '' ? $instansi : 'Admin SiNemu',
                            'manager_label' => 'Admin Pengelola',
                            'address' => $address,
                            'kecamatan' => $kecamatan,
                            'lat' => $hasLat && $admin->lat !== null ? (float) $admin->lat : null,
                            'lng' => $hasLng && $admin->lng !== null ? (float) $admin->lng : null,
                            'phone' => '0812-3456-7890',
                            'hours' => '08.00-20.00 WIB',
                        ];
                    })
                    ->values()
                    ->all();
            }, []);
        }
        return $pickupLocations;
    }

    private function getClaimableLostReports(): \Illuminate\Support\Collection
    {
        if (!$this->hasDatabaseTable('laporan_barang_hilangs')) {
            return collect();
        }
        
        return $this->safeDatabaseCall(function () {
                $query = LaporanBarangHilang::query()
                    ->where('user_id', (int) Auth::id())
                    ->select([
                        'id',
                        'nama_barang',
                        'lokasi_hilang',
                        'tanggal_hilang',
                        'kontak_pelapor',
                        'bukti_kepemilikan',
                    ]);

                if ($this->hasDatabaseColumn('laporan_barang_hilangs', 'sumber_laporan')) {
                    $query->where('sumber_laporan', 'lapor_hilang');
                }
                if ($this->hasDatabaseColumn('laporan_barang_hilangs', 'status_laporan')) {
                    $query->whereIn('status_laporan', [
                        WorkflowStatus::REPORT_APPROVED,
                        WorkflowStatus::REPORT_MATCHED,
                        WorkflowStatus::REPORT_CLAIMED,
                    ]);
                }

                return $query
                    ->orderByDesc('tanggal_hilang')
                    ->orderByDesc('updated_at')
                    ->get();
            }, collect());
    }

    public function showLostDetail(LaporanBarangHilang $laporanBarangHilang)
    {
        $isVisible = $this->canAccessPublicDetail(
            (bool) ($laporanBarangHilang->tampil_di_home ?? false),
            'laporan_barang_hilangs'
        );
        abort_unless($isVisible, 404);

        [$lostStatusLabel, $lostStatusClass] = match ((string) ($laporanBarangHilang->status_laporan ?? WorkflowStatus::REPORT_SUBMITTED)) {
            WorkflowStatus::REPORT_APPROVED => ['Terverifikasi', 'is-in-progress'],
            WorkflowStatus::REPORT_MATCHED => ['Sudah Dicocokkan', 'is-found'],
            WorkflowStatus::REPORT_CLAIMED => ['Sedang Diproses Klaim', 'is-in-progress'],
            WorkflowStatus::REPORT_COMPLETED => ['Selesai', 'is-returned'],
            WorkflowStatus::REPORT_REJECTED => ['Ditolak Admin', 'is-returned'],
            default => ['Menunggu Verifikasi', 'is-in-progress'],
        };

        $pelapor = $laporanBarangHilang->user?->nama ?? $laporanBarangHilang->user?->name ?? 'Pengguna';
        $detail = (object) [
            'type' => 'hilang',
            'title' => (string) $laporanBarangHilang->nama_barang,
            'category' => 'Umum',
            'location' => (string) $laporanBarangHilang->lokasi_hilang,
            'date_label' => $laporanBarangHilang->tanggal_hilang
                ? Carbon::parse((string) $laporanBarangHilang->tanggal_hilang)->translatedFormat('d F Y')
                : '-',
            'status_label' => $lostStatusLabel,
            'status_class' => $lostStatusClass,
            'description' => trim((string) ($laporanBarangHilang->keterangan ?? '')) !== ''
                ? (string) $laporanBarangHilang->keterangan
                : 'Belum ada deskripsi tambahan dari pelapor.',
            'reporter' => $pelapor,
            'image_url' => $this->resolveItemImageUrl((string) ($laporanBarangHilang->foto_barang ?? ''), 'barang-hilang'),
        ];

        return view('home.detail', [
            'pageTitle' => 'Detail Barang Hilang - SiNemu',
            'detail' => $detail,
        ]);
    }

    public function showFoundDetail(Barang $barang)
    {
        $isVisible = $this->canAccessPublicDetail(
            (bool) ($barang->tampil_di_home ?? false),
            'barangs'
        );
        abort_unless($isVisible, 404);

        $statusBarang = (string) ($barang->status_barang ?? '');
        $statusMeta = match ($statusBarang) {
            'dalam_proses_klaim' => [
                'label' => 'Sedang Diproses Klaim',
                'class' => 'is-in-progress',
                'claimable' => false,
                'subtitle' => 'Barang ini sedang dalam proses verifikasi klaim. Pengajuan klaim baru tidak dapat dilakukan saat ini.',
            ],
            'sudah_diklaim' => [
                'label' => 'Sudah Diklaim',
                'class' => 'is-claimed',
                'claimable' => false,
                'subtitle' => 'Barang ini sudah melalui proses klaim dan tidak tersedia untuk pengajuan klaim baru.',
            ],
            'sudah_dikembalikan' => [
                'label' => 'Sudah Dikembalikan',
                'class' => 'is-returned',
                'claimable' => false,
                'subtitle' => 'Barang ini sudah dikembalikan kepada pemilik dan tidak tersedia untuk klaim baru.',
            ],
            default => [
                'label' => 'Tersedia untuk Diklaim',
                'class' => 'is-found',
                'claimable' => true,
                'subtitle' => 'Detail laporan barang temuan untuk membantu pengguna memahami informasi sebelum tindak lanjut.',
            ],
        };

        $claimActionUrl = route('home') . '#hilang-temuan';
        $claimActionLabel = Auth::check() ? 'Ajukan Klaim' : 'Ajukan Klaim';
        if ($statusBarang === 'dalam_proses_klaim') {
            $claimActionUrl = route('user.claim-history');
            $claimActionLabel = 'Lihat Status Klaim';
        } elseif ($statusBarang === 'sudah_diklaim') {
            $claimActionUrl = route('user.claim-history');
            $claimActionLabel = 'Lihat Instruksi Pengambilan';
        } elseif ($statusBarang === 'sudah_dikembalikan') {
            $claimActionUrl = route('user.claim-history');
            $claimActionLabel = 'Lihat Riwayat Klaim';
        }

        $penanggungJawab = $barang->admin?->instansi ?? $barang->admin?->nama ?? 'Admin';
        $detail = (object) [
            'id' => (int) $barang->id,
            'type' => 'temuan',
            'title' => (string) $barang->nama_barang,
            'category' => ucwords(strtolower((string) ($barang->kategori?->nama_kategori ?? 'Umum'))),
            'location' => (string) $barang->lokasi_ditemukan,
            'date_label' => $barang->tanggal_ditemukan
                ? Carbon::parse((string) $barang->tanggal_ditemukan)->translatedFormat('d F Y')
                : '-',
            'status_label' => $statusMeta['label'],
            'status_class' => $statusMeta['class'],
            'description' => trim((string) ($barang->deskripsi ?? '')) !== ''
                ? (string) $barang->deskripsi
                : 'Belum ada deskripsi tambahan.',
            'reporter' => $penanggungJawab,
            'image_url' => $this->resolveItemImageUrl((string) ($barang->foto_barang ?? ''), 'barang-temuan'),
            'subtitle' => $statusMeta['subtitle'],
            'is_claimable' => $statusMeta['claimable'],
            'claim_action_url' => $claimActionUrl,
            'claim_action_label' => $claimActionLabel,
            'preclaim_note' => 'Kecocokan barang tidak otomatis membuktikan kepemilikan. Anda wajib mengajukan klaim dengan bukti kepemilikan untuk diverifikasi admin.',
        ];

        return view('home.detail', [
            'pageTitle' => 'Detail Barang Temuan - SiNemu',
            'detail' => $detail,
        ]);
    }

    private function resolveItemImageUrl(string $fotoPath, string $defaultFolder): string
    {
        $cleanPath = str_replace('\\', '/', trim($fotoPath, '/'));
        if ($cleanPath === '') {
            return asset('img/login-image.png');
        }

        if (Str::startsWith($cleanPath, ['http://', 'https://'])) {
            return $cleanPath;
        }

        if (Str::startsWith($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, 8);
        } elseif (Str::startsWith($cleanPath, 'public/')) {
            $cleanPath = substr($cleanPath, 7);
        }

        [$folder, $subPath] = array_pad(explode('/', $cleanPath, 2), 2, '');

        if (in_array($folder, ['barang-hilang', 'barang-temuan', 'verifikasi-klaim'], true) && $subPath !== '') {
            $relative = $folder . '/' . $subPath;
            $dataUri = $this->buildImageDataUri($relative);
            if ($dataUri !== null) {
                return $dataUri;
            }
            $version = Storage::disk('public')->exists($relative)
                ? (string) @filemtime(Storage::disk('public')->path($relative))
                : null;
            $url = Storage::disk('public')->exists($relative)
                ? asset('storage/' . $relative)
                : route('media.image', ['folder' => $folder, 'path' => $subPath]);
            return $version ? ($url . '?v=' . $version) : $url;
        }

        if ($subPath !== '') {
            $relative = $defaultFolder . '/' . $cleanPath;
            if (Storage::disk('public')->exists($relative)) {
                $dataUri = $this->buildImageDataUri($relative);
                if ($dataUri !== null) {
                    return $dataUri;
                }
                $version = (string) @filemtime(Storage::disk('public')->path($relative));
                $url = asset('storage/' . $relative);
                return $version ? ($url . '?v=' . $version) : $url;
            }

            return asset('img/login-image.png');
        }

        if (Storage::disk('public')->exists($cleanPath)) {
            return asset('storage/' . $cleanPath);
        }

        if (Storage::disk('public')->exists($defaultFolder . '/' . $cleanPath)) {
            $relative = $defaultFolder . '/' . $cleanPath;
            $dataUri = $this->buildImageDataUri($relative);
            if ($dataUri !== null) {
                return $dataUri;
            }
            $version = (string) @filemtime(Storage::disk('public')->path($relative));
            $url = asset('storage/' . $relative);
            return $version ? ($url . '?v=' . $version) : $url;
        }

        return asset('img/login-image.png');
    }

    private function buildImageDataUri(string $relativePath): ?string
    {
        if (!Storage::disk('public')->exists($relativePath)) {
            return null;
        }

        $absolutePath = Storage::disk('public')->path($relativePath);
        $mimeType = mime_content_type($absolutePath) ?: 'image/jpeg';
        if (!is_string($mimeType) || !str_starts_with($mimeType, 'image/')) {
            $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
            $mimeType = match ($extension) {
                'webp' => 'image/webp',
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                default => 'image/jpeg',
            };
        }
        $size = @filesize($absolutePath);
        if ($size === false || $size > 2 * 1024 * 1024) {
            return null;
        }

        $binary = @file_get_contents($absolutePath);
        if ($binary === false) {
            return null;
        }

        return 'data:' . $mimeType . ';base64,' . base64_encode($binary);
    }

    private function resolveUserDisplayName(mixed $user): string
    {
        if (!$user) {
            return 'Pengguna';
        }

        $username = trim((string) ($user->username ?? ''));
        if ($username !== '') {
            return $username;
        }

        $nama = trim((string) ($user->nama ?? $user->name ?? ''));
        if ($nama !== '') {
            return $nama;
        }

        return 'Pengguna';
    }

    private function resolveUserAvatarUrl(?string $profilePath): string
    {
        $defaultAvatar = asset('img/profil.jpg');
        $profilePath = trim((string) $profilePath);
        if ($profilePath === '') {
            return $defaultAvatar;
        }

        if (str_starts_with($profilePath, 'http://') || str_starts_with($profilePath, 'https://')) {
            return $profilePath;
        }

        $normalized = str_replace('\\', '/', ltrim($profilePath, '/'));
        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, 8);
        } elseif (str_starts_with($normalized, 'public/')) {
            $normalized = substr($normalized, 7);
        }

        [$folder, $subPath] = array_pad(explode('/', $normalized, 2), 2, '');
        if (in_array($folder, ['profil-admin', 'profil-user', 'barang-hilang', 'barang-temuan', 'verifikasi-klaim'], true) && $subPath !== '') {
            if (Storage::disk('public')->exists($normalized)) {
                $dataUri = $this->buildImageDataUri($normalized);
                if ($dataUri !== null) {
                    return $dataUri;
                }

                return route('media.image', ['folder' => $folder, 'path' => $subPath]);
            }

            return $defaultAvatar;
        }

        if (Storage::disk('public')->exists($normalized)) {
            $dataUri = $this->buildImageDataUri($normalized);
            if ($dataUri !== null) {
                return $dataUri;
            }

            return asset('storage/' . $normalized);
        }

        return $defaultAvatar;
    }

    private function normalizeLocationLabel(string $location): string
    {
        $location = trim(preg_replace('/\s+/', ' ', $location) ?? '');
        if ($location === '') {
            return '-';
        }

        $lower = Str::lower($location);

        if (Str::startsWith($lower, 'kec ')) {
            $district = trim(substr($location, 4));
            return 'Kecamatan ' . ucwords(Str::lower($district));
        }

        if (Str::startsWith($lower, 'kecamatan ')) {
            $district = trim(substr($location, 10));
            return 'Kecamatan ' . ucwords(Str::lower($district));
        }

        if (Str::startsWith($lower, 'kel ')) {
            $ward = trim(substr($location, 4));
            return 'Kelurahan ' . ucwords(Str::lower($ward));
        }

        if (Str::startsWith($lower, 'kelurahan ')) {
            $ward = trim(substr($location, 10));
            return 'Kelurahan ' . ucwords(Str::lower($ward));
        }

        return ucwords(Str::lower($location));
    }

    private function resolveHomeScopeQuery($baseQuery, string $tableName)
    {
        if ($tableName === 'laporan_barang_hilangs' && $this->hasDatabaseColumn('laporan_barang_hilangs', 'status_laporan')) {
            $baseQuery->whereIn('status_laporan', [
                WorkflowStatus::REPORT_APPROVED,
                WorkflowStatus::REPORT_MATCHED,
                WorkflowStatus::REPORT_CLAIMED,
            ]);
        }

        if ($tableName === 'barangs' && $this->hasDatabaseColumn('barangs', 'status_laporan')) {
            $baseQuery->whereIn('status_laporan', [
                WorkflowStatus::REPORT_APPROVED,
                WorkflowStatus::REPORT_MATCHED,
                WorkflowStatus::REPORT_CLAIMED,
            ]);
        }

        if ($tableName === 'barangs' && $this->hasDatabaseColumn('barangs', 'status_barang')) {
            $baseQuery->where('status_barang', '!=', 'sudah_dikembalikan');
        }

        return $baseQuery;
    }

    private function canAccessPublicDetail(bool $isPublished, string $tableName): bool
    {
        if ($tableName === 'laporan_barang_hilangs' && $this->hasDatabaseColumn('laporan_barang_hilangs', 'status_laporan')) {
            return $isPublished;
        }

        if ($tableName === 'barangs' && $this->hasDatabaseColumn('barangs', 'status_laporan')) {
            return $isPublished;
        }

        return $isPublished;
    }

    private function publishedItemsCount(string $tableName): int
    {
        if ($tableName === 'laporan_barang_hilangs') {
            return LaporanBarangHilang::query()->where('tampil_di_home', true)->count();
        }

        if ($tableName === 'barangs') {
            return Barang::query()->where('tampil_di_home', true)->count();
        }

        return 0;
    }

    private function hasDatabaseTable(string $table): bool
    {
        return $this->safeDatabaseCall(fn () => Schema::hasTable($table), false);
    }

    private function hasDatabaseColumn(string $table, string $column): bool
    {
        return $this->safeDatabaseCall(fn () => Schema::hasColumn($table, $column), false);
    }

    private function safeDatabaseCall(callable $callback, mixed $fallback): mixed
    {
        if ($this->skipDatabaseCalls) {
            return $fallback;
        }

        if (!$this->databaseReachabilityChecked) {
            $this->databaseReachabilityChecked = true;

            if (!$this->isDatabaseSocketReachable()) {
                $this->skipDatabaseCalls = true;

                return $fallback;
            }
        }

        try {
            return $callback();
        } catch (Throwable $exception) {
            $this->skipDatabaseCalls = true;

            if (!$this->databaseFailureReported) {
                report($exception);
                $this->databaseFailureReported = true;
            }

            return $fallback;
        }
    }

    private function isDatabaseSocketReachable(): bool
    {
        $defaultConnection = (string) config('database.default', 'mysql');
        $connection = (array) config('database.connections.' . $defaultConnection, []);
        $driver = (string) ($connection['driver'] ?? '');

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return true;
        }

        $host = (string) ($connection['host'] ?? '');
        $port = (int) ($connection['port'] ?? 3306);

        if ($host === '' || $port <= 0) {
            return false;
        }

        if (!in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return true;
        }

        $socket = @fsockopen($host, $port, $errno, $errstr, 0.25);
        if (is_resource($socket)) {
            fclose($socket);

            return true;
        }

        return false;
    }
}
