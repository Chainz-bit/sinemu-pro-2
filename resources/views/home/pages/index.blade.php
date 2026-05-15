@extends('layouts.main')
@section('content')
    @php
        $managerRoleLabel = \App\Support\RoleLabels::manager();
        $managerRoleLabelLower = \App\Support\RoleLabels::managerLower();
    @endphp
    {{-- ========================================= --}}
    {{-- Main Content Container Start --}}
    {{-- ========================================= --}}
    <div class="container-fluid px-0 pb-5 home-main">
        {{-- ========================================= --}}
        {{-- Navbar Start --}}
        {{-- ========================================= --}}
        <nav class="navbar navbar-expand-lg floating-nav fixed-nav px-3 px-lg-4" id="mainNavBar">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold order-1" href="{{ route('home') }}" data-home-link>
                <img src="{{ asset('img/logo.png') }}" alt="Sinemu" class="brand-logo" width="160" height="50" fetchpriority="high">
            </a>
            <button class="navbar-toggler border-0 shadow-none order-2 ms-auto d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <iconify-icon icon="mdi:menu" aria-hidden="true"></iconify-icon>
            </button>

            <div class="collapse navbar-collapse order-4 order-lg-2" id="mainNav">
                @auth('web')
                    <div class="dropdown profile-dropdown nav-profile order-1 order-lg-2">
                        <button class="user-chip profile-chip profile-toggle d-none d-lg-inline-flex" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="profile-text">
                                <div class="fw-semibold small">{{ $userName ?? 'Pengguna' }}</div>
                                <div class="text-muted xsmall">{{ $userLocation ?? 'Lokasi Anda' }}</div>
                            </div>
                            <span class="avatar avatar-img">
                                <img src="{{ $userAvatar ?? asset('img/profil.jpg') }}" alt="Profil {{ $userName ?? 'Pengguna' }}" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='{{ asset('img/profil.jpg') }}';">
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end profile-menu">
                            <li>
                                <a class="dropdown-item" href="{{ route('user.dashboard') }}">
                                    Dashboard
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger">
                                        Logout
                                    </button>
                                </form>
                            </li>
                        </ul>

                        <button
                            class="user-chip profile-chip profile-toggle-mobile d-lg-none w-100"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#mobileProfileSubmenu"
                            aria-expanded="false"
                            aria-controls="mobileProfileSubmenu"
                        >
                            <div class="profile-text">
                                <div class="fw-semibold small">{{ $userName ?? 'Pengguna' }}</div>
                                <div class="text-muted xsmall">{{ $userLocation ?? 'Lokasi Anda' }}</div>
                            </div>
                            <span class="avatar avatar-img">
                                <img src="{{ $userAvatar ?? asset('img/profil.jpg') }}" alt="Profil {{ $userName ?? 'Pengguna' }}" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='{{ asset('img/profil.jpg') }}';">
                            </span>
                        </button>
                        <div id="mobileProfileSubmenu" class="collapse profile-submenu-mobile d-lg-none">
                            <a class="profile-submenu-item" href="{{ route('user.dashboard') }}">
                                Dashboard
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="profile-submenu-item text-danger">
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="nav-profile nav-guest order-1 order-lg-2">
                        <button class="btn btn-sinemu btn-sinemu-primary btn-sm px-3 py-2" type="button" data-bs-toggle="modal" data-bs-target="#loginPortalModal">
                            Masuk
                        </button>
                    </div>
                @endauth

                <ul class="navbar-nav mx-auto mb-0 mb-lg-0 gap-lg-4 nav-centered order-2 order-lg-1">
                    <li class="nav-item"><a class="nav-link" href="#hero">Pencarian</a></li>
                    <li class="nav-item"><a class="nav-link" href="#hilang-temuan">Hilang &amp; Temuan</a></li>
                    <li class="nav-item"><a class="nav-link" href="#klaim">Tutorial</a></li>
                    <li class="nav-item"><a class="nav-link" href="#lokasi">Lokasi</a></li>
                    <li class="nav-item"><a class="nav-link" href="#kontak">Kontak</a></li>
                </ul>
            </div>
        </nav>
        {{-- Navbar End --}}

        {{-- ========================================= --}}
        {{-- Login Portal Modal Start --}}
        {{-- ========================================= --}}
        @guest('web')
            <div class="modal fade login-portal-modal" id="loginPortalModal" tabindex="-1" aria-labelledby="loginPortalModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <button type="button" class="btn-close login-portal-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        <div class="modal-body p-0">
                            <div class="login-portal-head text-center">
                                <img src="{{ asset('img/logo.png') }}" alt="Sinemu" class="login-portal-logo" loading="lazy" decoding="async">
                                <h2 id="loginPortalModalLabel" class="login-portal-title">Masuk ke SiNemu</h2>
                                <p class="login-portal-subtitle mb-0">Pilih peran Anda untuk melanjutkan akses portal.</p>
                            </div>

                            <a href="{{ url('/login') }}" class="login-portal-option">
                                <span class="login-portal-option-icon"><i class="fa-regular fa-user"></i></span>
                                <span class="login-portal-option-text">
                                    <strong>Pencari Barang</strong>
                                    <small>Cari barang yang hilang atau lapor kehilangan</small>
                                </span>
                                <i class="fa-solid fa-chevron-right login-portal-arrow"></i>
                            </a>

                            <a href="{{ manager_route('login') }}" class="login-portal-option">
                                <span class="login-portal-option-icon"><i class="fa-regular fa-id-badge"></i></span>
                                <span class="login-portal-option-text">
                                    <strong>{{ $managerRoleLabel }}</strong>
                                    <small>Kelola laporan barang temuan dan verifikasi klaim</small>
                                </span>
                                <i class="fa-solid fa-chevron-right login-portal-arrow"></i>
                            </a>

                        </div>
                    </div>
                </div>
            </div>
        @endguest
        {{-- Login Portal Modal End --}}

        {{-- ========================================= --}}
        {{-- Hero + Filter Start --}}
        {{-- ========================================= --}}
        @auth('web')
            @if(session('status'))
                <div class="container mt-3">
                    <div class="alert alert-success mb-0">{{ session('status') }}</div>
                </div>
            @endif
            @if(session('error'))
                <div class="container mt-3">
                    <div class="alert alert-danger mb-0">{{ session('error') }}</div>
                </div>
            @endif
            @if($errors->any())
                <div class="container mt-3">
                    <div class="alert alert-danger mb-0">{{ $errors->first() }}</div>
                </div>
            @endif
        @endauth

        <section id="hero" class="section-space hero-modern-section">
            <div class="hero-modern text-center" data-animate="1">
                <h1 class="hero-modern-title mb-3">Kehilangan atau Menemukan <span>Barang</span> di <span>Indramayu?</span></h1>
                <p class="hero-modern-subtitle mb-4">SiNemu bantu temukan barang berhargamu. Lapor dengan mudah, cari dengan cepat, dan klaim dengan aman.</p>
                <div class="hero-modern-actions">
                    <a href="#hilang-temuan" class="btn btn-sinemu btn-sinemu-primary">Cari Barang Sekarang</a>
                    @auth('web')
                        <a href="{{ route('user.lost-reports.create') }}" class="btn btn-sinemu btn-sinemu-outline">Lapor Kehilangan</a>
                    @else
                        <button type="button" class="btn btn-sinemu btn-sinemu-outline" data-bs-toggle="modal" data-bs-target="#loginPortalModal">Lapor Kehilangan</button>
                    @endauth
                </div>
            </div>

            <div class="surface-card filter-wrap" data-animate="2">
                <div class="filter-header">
                    <div class="filter-head-left">
                        <span class="filter-icon">
                            <img src="{{ asset('img/icon filter.png') }}" alt="Filter" class="filter-icon-img" loading="lazy" decoding="async">
                        </span>
                        <div>
                            <p class="filter-title mb-2">Filter Pencarian Cepat</p>
                            <p class="filter-subtitle mb-4">Saring hasil pencarian berdasarkan kategori, waktu, dan lokasi secara spesifik.</p>
                        </div>
                    </div>
                    <button class="chevron-btn" type="button"><i class="fa-solid fa-chevron-up"></i></button>
                </div>

                <form id="filterForm" class="row g-3 align-items-end" novalidate>
                    <div class="col-md-6 col-xl position-relative filter-keyword">
                        <label for="keywordInput" class="form-label ps-2 py-2">KATA KUNCI</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input id="keywordInput" type="text" class="form-control" placeholder="Dompet, Kunci, HP..." autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
                        </div>
                    </div>
                    <div class="col-md-6 col-xl">
                        <label for="categorySelect" class="form-label ps-2 py-2">KATEGORI</label>
                        <div class="filter-dropdown" data-filter-dropdown>
                            <select id="categorySelect" class="native-filter-select-hidden" tabindex="-1" aria-hidden="true">
                                @foreach ($categories as $category)
                                    <option value="{{ $category }}">{{ $category }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="form-select filter-select filter-dropdown-toggle" data-filter-dropdown-toggle>
                                <span data-filter-dropdown-label>{{ $categories[0] ?? 'Semua Kategori' }}</span>
                            </button>
                            <ul class="filter-dropdown-menu" data-filter-dropdown-menu>
                                @foreach ($categories as $category)
                                    <li>
                                        <button type="button" class="filter-option {{ $loop->first ? 'is-active' : '' }}" data-filter-value="{{ $category }}">
                                            {{ $category }}
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl">
                        <label for="dateInput" class="form-label ps-2 py-2">WAKTU PENEMUAN</label>
                        <div class="input-with-icon input-with-icon-date">
                            <i class="fa-regular fa-calendar"></i>
                            <input id="dateInput" type="text" class="form-control modern-date-input" placeholder="dd/mm/yyyy" inputmode="numeric" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-6 col-xl">
                        <label for="regionSelect" class="form-label ps-2 py-2">WILAYAH</label>
                        <div class="filter-dropdown" data-filter-dropdown>
                            <select id="regionSelect" class="native-filter-select-hidden" tabindex="-1" aria-hidden="true">
                                @foreach ($regions as $region)
                                    <option value="{{ $region }}">{{ $region }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="form-select filter-select filter-dropdown-toggle" data-filter-dropdown-toggle>
                                <span data-filter-dropdown-label>{{ $regions[0] ?? 'Seluruh Wilayah' }}</span>
                            </button>
                            <ul class="filter-dropdown-menu" data-filter-dropdown-menu>
                                @foreach ($regions as $region)
                                    <li>
                                        <button type="button" class="filter-option {{ $loop->first ? 'is-active' : '' }}" data-filter-value="{{ $region }}">
                                            {{ $region }}
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-2 d-flex">
                        <button id="searchButton" class="btn btn-sinemu btn-sinemu-primary w-100 filter-search-btn" type="submit">
                            <i class="fa-solid fa-magnifying-glass me-2"></i>Cari
                        </button>
                    </div>
                    <div class="col-12">
                        <div id="filterFormFeedback" class="filter-form-feedback" role="status" aria-live="polite"></div>
                    </div>
                </form>
            </div>
        </section>
        {{-- Hero + Filter End --}}

        {{-- ========================================= --}}
        {{-- Daftar Barang Start --}}
        {{-- ========================================= --}}
        <section id="hilang-temuan" class="section-space pt-0">
            <div class="item-block surface-card p-3 p-lg-4 mb-4">
                <div class="item-block-head mb-3">
                    <div>
                        <div class="item-kicker item-kicker-danger">Informasi Terkini</div>
                        <h2 class="item-block-title mb-0 fs-5">Daftar Barang Hilang Terkini</h2>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="carousel-nav-btn" data-carousel-target="lostItemsList" data-carousel-dir="prev" aria-label="Sebelumnya">
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>
                        <button type="button" class="carousel-nav-btn" data-carousel-target="lostItemsList" data-carousel-dir="next" aria-label="Berikutnya">
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    </div>
                </div>

                <div class="carousel-track" id="lostItemsList">
                    @forelse ($lostItems as $item)
                        <article class="carousel-item item-card-v2 filter-item"
                            data-list="lost"
                            data-category="{{ strtoupper($item['category']) }}"
                            data-name="{{ strtolower($item['name']) }}"
                            data-region="{{ strtolower($item['location']) }}"
                            data-date="{{ $item['date'] }}">
                            <div class="item-media">
                                <img src="{{ $item['image_url'] ?? asset('img/login-image.png') }}" alt="{{ $item['name'] }}" loading="lazy" decoding="async" width="600" height="360" onerror="this.onerror=null;this.src='{{ asset('img/login-image.png') }}';">
                                <span class="item-status {{ $item['status_class'] ?? 'item-status-danger' }}">{{ $item['status_label'] ?? 'Belum Ditemukan' }}</span>
                            </div>
                            <div class="item-body">
                                <h3 class="item-name">{{ $item['name'] }}</h3>
                                <p class="item-category"><i class="fa-solid fa-tag"></i> {{ ucwords(strtolower($item['category'])) }}</p>
                                <p class="item-meta"><i class="fa-solid fa-location-dot"></i> {{ $item['location'] }}</p>
                                <p class="item-meta"><i class="fa-regular fa-clock"></i> {{ $item['date_label'] ?? $item['date'] }}</p>
                                <a href="{{ $item['detail_url'] }}" class="btn item-action-btn">Lihat Detail Laporan</a>
                            </div>
                        </article>
                    @empty
                    @endforelse
                </div>
                <div id="lostEmptyState" class="empty-state empty-state-lost mt-3" role="status" aria-live="polite">
                    <div class="empty-state-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
                    <div class="empty-state-body">
                        <h3 class="empty-state-title">Belum ada barang hilang sesuai filter</h3>
                        <p class="empty-state-text">Coba ubah kata kunci, kategori, waktu, atau wilayah pencarian untuk melihat laporan lainnya.</p>
                    </div>
                </div>
            </div>

            <div class="item-block surface-card p-3 p-lg-4">
                <div class="item-block-head mb-3">
                    <div>
                        <div class="item-kicker item-kicker-success">Update Terbaru</div>
                        <h2 class="item-block-title mb-0 fs-5">Barang Temuan Terbaru</h2>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="carousel-nav-btn" data-carousel-target="foundItemsList" data-carousel-dir="prev" aria-label="Sebelumnya">
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>
                        <button type="button" class="carousel-nav-btn" data-carousel-target="foundItemsList" data-carousel-dir="next" aria-label="Berikutnya">
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    </div>
                </div>

                <div class="carousel-track" id="foundItemsList">
                    @forelse ($foundItems as $item)
                        @php
                            $statusKey = (string) ($item['claim_status_key'] ?? 'available');
                            $statusClass = match ($statusKey) {
                                'in_progress' => 'item-status-warning',
                                'claimed', 'returned' => 'item-status-muted',
                                default => 'item-status-success',
                            };
                            $isClaimable = (bool) ($item['is_claimable'] ?? true);
                            $claimButtonLabel = $isClaimable ? 'Klaim Barang Ini' : 'Tidak Tersedia untuk Klaim';
                        @endphp
                        <article class="carousel-item item-card-v2 filter-item"
                            data-list="found"
                            data-category="{{ strtoupper($item['category']) }}"
                            data-name="{{ strtolower($item['name']) }}"
                            data-region="{{ strtolower($item['location']) }}"
                            data-date="{{ $item['date'] }}">
                            <div class="item-media">
                                <img src="{{ $item['image_url'] ?? asset('img/login-image.png') }}" alt="{{ $item['name'] }}" loading="lazy" decoding="async" width="600" height="360" onerror="this.onerror=null;this.src='{{ asset('img/login-image.png') }}';">
                                <span class="item-status {{ $statusClass }}">{{ $item['claim_status_label'] ?? 'Tersedia untuk Diklaim' }}</span>
                            </div>
                            <div class="item-body">
                                <h3 class="item-name">{{ $item['name'] }}</h3>
                                <p class="item-meta"><i class="fa-solid fa-location-dot"></i> {{ $item['location'] }}</p>
                                <p class="item-meta"><i class="fa-regular fa-clock"></i> {{ $item['date_label'] ?? $item['date'] }}</p>
                                <a href="{{ $item['detail_url'] }}" class="item-detail-link">Lihat Detail Laporan</a>
                                @auth('web')
                                    @if($isClaimable)
                                        <a
                                            class="btn item-action-btn"
                                            href="{{ route('user.claims.create', ['barang_id' => $item['id']]) }}"
                                        >
                                            {{ $claimButtonLabel }}
                                        </a>
                                    @else
                                        <button class="btn item-action-btn item-action-btn-disabled" type="button" disabled aria-disabled="true">
                                            {{ $claimButtonLabel }}
                                        </button>
                                    @endif
                                @else
                                    @if($isClaimable)
                                        <button class="btn item-action-btn" type="button" data-bs-toggle="modal" data-bs-target="#loginPortalModal">{{ $claimButtonLabel }}</button>
                                    @else
                                        <button class="btn item-action-btn item-action-btn-disabled" type="button" disabled aria-disabled="true">
                                            {{ $claimButtonLabel }}
                                        </button>
                                    @endif
                                @endauth
                            </div>
                        </article>
                    @empty
                    @endforelse
                </div>
                <div id="foundEmptyState" class="empty-state empty-state-found mt-3" role="status" aria-live="polite">
                    <div class="empty-state-icon"><i class="fa-regular fa-map"></i></div>
                    <div class="empty-state-body">
                        <h3 class="empty-state-title">Belum ada barang temuan sesuai filter</h3>
                        <p class="empty-state-text">Perluas wilayah atau kosongkan filter untuk melihat semua barang temuan terbaru.</p>
                    </div>
                </div>
            </div>
        </section>
        {{-- Daftar Barang End --}}

        {{-- ========================================= --}}
        {{-- Prosedur Klaim Start --}}
        {{-- ========================================= --}}
        <section id="klaim" class="section-space pt-0">
            <div class="surface-card claim-card p-4 p-lg-5">
                <div class="text-center mb-4">
                    <h2 class="section-title mb-0">
                        <img src="{{ asset('img/Icon-verifikaasi.png') }}" alt="Verifikasi" class="klaim-title-icon" loading="lazy" decoding="async">
                        Prosedur Klaim Barang
                    </h2>
                </div>
                <div class="row g-4 claim-grid">
                    <div class="col-md-6">
                        <div class="claim-item">
                            <div class="step-number">1</div>
                            <div>
                                <h3 class="h5 fw-bold mb-2">Temukan &amp; Identifikasi</h3>
                                <p class="text-muted fs-6 mb-0">Cari barang Anda di daftar temuan dan catat kode unik barang untuk proses verifikasi lebih cepat oleh sistem kami.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="claim-item">
                            <div class="step-number">2</div>
                            <div>
                                <h3 class="h5 fw-bold mb-2">Unggah Bukti Sah</h3>
                                <p class="text-muted fs-6 mb-0">Siapkan foto barang saat masih dimiliki, kuitansi pembelian, atau jelaskan ciri fisik mendetail yang tidak terlihat di foto.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="claim-item">
                            <div class="step-number">3</div>
                            <div>
                                <h3 class="h5 fw-bold mb-2">Verifikasi {{ $managerRoleLabel }}</h3>
                                <p class="text-muted fs-6 mb-0">Tim {{ $managerRoleLabelLower }} kami akan memproses klaim Anda maksimal 24 jam untuk memastikan keabsahan kepemilikan barang.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="claim-item">
                            <div class="step-number">4</div>
                            <div>
                                <h3 class="h5 fw-bold mb-2">Pengambilan Fisik</h3>
                                <p class="text-muted fs-6 mb-0">Datang ke lokasi kantor kecamatan yang telah ditentukan dengan membawa kartu identitas asli (KTP/SIM) sebagai bukti akhir.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="claim-outcome-note mt-4 text-center" role="note" aria-label="Catatan hasil verifikasi">
                    Klaim dapat <strong>disetujui</strong> atau <strong>ditolak</strong> jika bukti kepemilikan belum memadai.
                </div>
                <div class="claim-help mt-4 text-center">
                    <a href="https://wa.me/6281234567890" target="_blank" rel="noopener noreferrer" class="claim-help-link">
                        <i class="fa-brands fa-whatsapp me-2" aria-hidden="true"></i>Hubungi Support Center Kami
                    </a>
                </div>
            </div>
        </section>
        {{-- Prosedur Klaim End --}}

        {{-- ========================================= --}}
        {{-- Lokasi Pengambilan Start --}}
        {{-- ========================================= --}}
        <section id="lokasi" class="section-space pt-0">
            <div class="surface-card lokasi-wrap p-3 p-lg-4">
                <header class="lokasi-header">
                    <h2 class="lokasi-main-title mb-1">Lokasi Pengambilan Sinemu &ndash; Indramayu</h2>
                    <p class="lokasi-main-subtitle mb-0">Butuh bantuan klaim? Hubungi Support Center kami.</p>
                </header>

                <div class="lokasi-layout">
                    <div class="lokasi-map-panel">
                        <div id="pickupMap" class="lokasi-map-frame" aria-label="Peta lokasi pengambilan Sinemu Indramayu"></div>
                        <div class="lokasi-map-footer">
                            <span>Peta statis. Gunakan tombol Maps atau Rute untuk navigasi.</span>
                            <span>&copy; Sinemu Indramayu</span>
                        </div>
                    </div>

                    <aside class="lokasi-info-panel">
                        <h3 class="lokasi-title mb-2">Support & Lokasi Aktif</h3>
                        <p class="lokasi-subtitle mb-3">Pilih lokasi pengambilan terdekat, lalu buka peta atau dapatkan rute langsung.</p>

                        <div class="lokasi-contact-chip mb-3">
                            <span class="lokasi-chip-icon"><i class="fa-solid fa-lock"></i></span>
                            <div>
                                <small class="lokasi-manager-label" id="selectedLocationManager">{{ $managerRoleLabel }}</small>
                                <p class="mb-0 fw-bold" id="selectedLocationName">Sinemu Center Indramayu</p>
                                <small id="selectedLocationAddress">Jl. Jenderal Sudirman No. 88, Indramayu</small>
                            </div>
                        </div>

                        <div class="lokasi-meta mb-3">
                            <span id="selectedLocationHours">Jam Operasional: 08.00-20.00 WIB</span>
                            <span id="selectedLocationDistance">Jarak: aktifkan Lokasi Saya untuk estimasi.</span>
                        </div>

                        <div class="lokasi-actions mb-3">
                            <a id="selectedOpenMaps" class="lokasi-action-btn lokasi-action-primary" href="#" target="_blank" rel="noopener">
                                <i class="fa-regular fa-map me-2"></i>Buka di Maps
                            </a>
                            <button id="selectedGetRoute" type="button" class="lokasi-action-btn lokasi-action-secondary mt-2">
                                <i class="fa-solid fa-route me-2"></i>Dapatkan Route
                            </button>
                            <button id="locateMeButton" type="button" class="lokasi-action-btn lokasi-action-light mt-2">
                                <i class="fa-solid fa-location-crosshairs me-2"></i>Lokasi Saya
                            </button>
                            <a
                                class="lokasi-action-btn lokasi-action-support mt-2"
                                href="https://wa.me/6281234567890?text=Halo%20Sinemu%20Support%20Indramayu%2C%20saya%20butuh%20bantuan%20proses%20klaim."
                                target="_blank"
                                rel="noopener"
                            >
                                <i class="fa-brands fa-whatsapp me-2"></i>Hubungi Support Center (0851-7438-6642)
                            </a>
                        </div>
                    </aside>
                </div>

                <div class="lokasi-list-wrap mt-3">
                    <div class="lokasi-list-head">
                        <h3 class="lokasi-list-title mb-0">Daftar Titik Pengambilan</h3>
                        <div class="lokasi-carousel-controls" aria-label="Navigasi carousel lokasi">
                            <button id="pickupCarouselPrev" type="button" class="lokasi-carousel-btn" aria-label="Geser ke kiri">
                                <i class="fa-solid fa-chevron-left"></i>
                            </button>
                            <button id="pickupCarouselNext" type="button" class="lokasi-carousel-btn" aria-label="Geser ke kanan">
                                <i class="fa-solid fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                    <div id="pickupLocationList" class="lokasi-list lokasi-carousel" aria-live="polite"></div>
                </div>
            </div>
        </section>
        {{-- Lokasi Pengambilan End --}}

        {{-- ========================================= --}}
        {{-- Contact Us Start --}}
        {{-- ========================================= --}}
        <section id="kontak" class="section-space pt-0">
            <div class="surface-card contact-section p-3 p-lg-4">
                <div class="text-center contact-head">
                    <h2 class="section-title mb-2">Kontak Kami</h2>
                    <p class="section-subtitle mx-auto mb-0">Punya pertanyaan atau masukan? Kirimkan pesan kepada kami!</p>
                </div>

                <div class="row g-3 g-lg-4 align-items-stretch">
                    <div class="col-lg-7">
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <article class="contact-info-card h-100">
                                    <span class="contact-info-icon bg-email"><i class="fa-regular fa-envelope"></i></span>
                                    <div class="contact-info-body">
                                        <p class="contact-info-label">EMAIL</p>
                                        <p class="contact-info-text mb-0">
                                            <a href="mailto:support@sinemu.id" class="contact-info-link">support@sinemu.id</a>
                                        </p>
                                        <p class="contact-info-note mb-0">Untuk bantuan klaim dan verifikasi data.</p>
                                    </div>
                                </article>
                            </div>
                            <div class="col-12 col-sm-6">
                                <article class="contact-info-card h-100">
                                    <span class="contact-info-icon bg-phone"><i class="fa-solid fa-phone"></i></span>
                                    <div class="contact-info-body">
                                        <p class="contact-info-label">TELEPON</p>
                                        <p class="contact-info-text mb-0">
                                            <a href="tel:+6285174386642" class="contact-info-link">0851-7438-6642</a>
                                        </p>
                                        <p class="contact-info-note mb-0">Respon lebih cepat pada jam operasional.</p>
                                    </div>
                                </article>
                            </div>
                            <div class="col-12 col-sm-6">
                                <article class="contact-info-card h-100">
                                    <span class="contact-info-icon bg-location"><i class="fa-solid fa-location-dot"></i></span>
                                    <div class="contact-info-body">
                                        <p class="contact-info-label">ALAMAT KANTOR</p>
                                        <p class="contact-info-text mb-0">Jl. Jenderal Sudirman No. 88, Indramayu</p>
                                        <p class="contact-info-note mb-0">Kunjungan terkait barang hanya sesuai arahan {{ $managerRoleLabelLower }}.</p>
                                    </div>
                                </article>
                            </div>
                            <div class="col-12 col-sm-6">
                                <article class="contact-info-card h-100">
                                    <span class="contact-info-icon bg-clock"><i class="fa-regular fa-clock"></i></span>
                                    <div class="contact-info-body">
                                        <p class="contact-info-label">JAM OPERASIONAL</p>
                                        <p class="contact-info-text mb-0">Senin - Jumat<br>08.00 - 16.00</p>
                                        <p class="contact-info-note mb-0">Pesan di luar jam kerja diproses hari berikutnya.</p>
                                    </div>
                                </article>
                            </div>
                            <div class="col-12">
                                <article class="contact-support-card">
                                    <div class="contact-support-main">
                                        <span class="contact-support-icon"><i class="fa-solid fa-headset"></i></span>
                                        <div>
                                            <h3 class="contact-support-title mb-1">Butuh bantuan cepat?</h3>
                                            <p class="contact-support-text mb-0">Sertakan nama barang, lokasi, dan nomor klaim jika ada agar tim Sinemu bisa mengecek lebih cepat.</p>
                                        </div>
                                    </div>
                                    <div class="contact-support-actions">
                                        <a href="https://wa.me/6285174386642?text=Halo%20Sinemu%20Support%2C%20saya%20butuh%20bantuan." target="_blank" rel="noopener" class="contact-support-btn contact-support-primary">
                                            <i class="fa-brands fa-whatsapp"></i>WhatsApp
                                        </a>
                                        <a href="mailto:support@sinemu.id" class="contact-support-btn contact-support-secondary">
                                            <i class="fa-regular fa-envelope"></i>Email
                                        </a>
                                    </div>
                                </article>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <form id="contactForm" class="contact-form-card h-100" novalidate>
                            <div class="contact-form-head">
                                <h3 class="contact-form-title mb-1">Kirim Pesan</h3>
                                <p class="contact-form-subtitle mb-0">Isi detail singkat agar tim support dapat membantu lebih tepat.</p>
                            </div>
                            <div class="mb-3">
                                <label for="contactName" class="form-label">Nama Lengkap</label>
                                <input id="contactName" name="name" type="text" class="form-control" placeholder="Nama lengkap" required>
                            </div>
                            <div class="mb-3">
                                <label for="contactEmail" class="form-label">Alamat Email</label>
                                <input id="contactEmail" name="email" type="email" class="form-control" placeholder="Email aktif" required>
                            </div>
                            <div class="mb-3">
                                <label for="contactPhone" class="form-label">Telepon</label>
                                <input id="contactPhone" name="phone" type="text" class="form-control" placeholder="Nomor telepon" required>
                            </div>
                            <div class="mb-3">
                                <label for="contactMessage" class="form-label">Pesan</label>
                                <textarea id="contactMessage" name="message" class="form-control contact-textarea" rows="5" placeholder="Tuliskan pesan Anda" required></textarea>
                            </div>
                            <div id="contactFormFeedback" class="contact-form-feedback" aria-live="polite"></div>
                            <button type="submit" class="btn btn-sinemu btn-sinemu-primary w-100 contact-submit-btn">
                                <i class="fa-solid fa-paper-plane me-2"></i>Kirim Pesan
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
        {{-- Contact Us End --}}

        @push('styles')
        @endpush

        @auth('web')
            <div class="modal fade" id="foundReportModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('user.found-reports.store') }}" class="modal-content">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Lapor Barang Temuan</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Nama Barang</label>
                                <input type="text" name="nama_barang" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Kategori</label>
                                <select name="kategori_id" class="form-select">
                                    <option value="">Pilih Kategori</option>
                                    @foreach(($kategoriOptions ?? collect()) as $kategori)
                                        <option value="{{ $kategori->id }}">{{ $kategori->nama_kategori }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Wilayah Ditemukan</label>
                                <select name="region_id" class="form-select" required>
                                    <option value="">Pilih Kecamatan</option>
                                    @foreach(($wilayahOptions ?? collect()) as $wilayah)
                                        <option value="{{ $wilayah->id }}">{{ $wilayah->nama_wilayah }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Lokasi Ditemukan</label>
                                <input type="text" name="lokasi_ditemukan" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tanggal Ditemukan</label>
                                <input type="date" name="tanggal_ditemukan" class="form-control" required>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Deskripsi</label>
                                <textarea name="deskripsi" class="form-control" rows="3" required></textarea>
                            </div>
                            <div class="mt-3">
                                <label class="form-label">No. WA Penemu</label>
                                <input type="text" name="kontak_penemu" class="form-control" placeholder="Contoh: 081234567890" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Kirim Temuan</button>
                        </div>
                    </form>
                </div>
            </div>

        @endauth

        <script id="pickupLocationsData" type="application/json">
            @json($pickupLocations ?? [])
        </script>
        <script>
            window.__SINEMU_ROLE_LABELS = @json([
                'managerDisplayName' => $managerRoleLabel,
                'managerDisplayNameLower' => $managerRoleLabelLower,
            ]);
        </script>

    </div>
    {{-- Main Content Container End --}}

    {{-- ========================================= --}}
    {{-- Footer Start --}}
    {{-- ========================================= --}}
    <footer class="footer-wrap footer-modern">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <h3 class="footer-title mb-3">Sinemu</h3>
                    <p class="footer-copy mb-4">Sistem Informasi Penemuan &amp; Pengambilan Barang Milik Umum. Berkomitmen membantu masyarakat dengan menjunjung tinggi integritas dan keterbukaan informasi.</p>
                    <div class="footer-social">
                        <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook"></i></a>
                        <a href="#" aria-label="Share"><i class="fa-solid fa-share-nodes"></i></a>
                        <a href="#" aria-label="Email"><i class="fa-regular fa-envelope"></i></a>
                    </div>
                </div>
                <div class="footer-links-wrap">
                    <div>
                        <div class="footer-head">PLATFORM</div>
                        <a href="#" class="footer-link">Tentang Kami</a>
                        <a href="#" class="footer-link">Cara Kerja</a>
                        <a href="#" class="footer-link">Statistik Layanan</a>
                        <a href="#" class="footer-link">Blog &amp; Berita</a>
                    </div>
                    <div>
                        <div class="footer-head">BANTUAN</div>
                        <a href="#" class="footer-link">Pusat Bantuan</a>
                        <a href="#" class="footer-link">Ketentuan Layanan</a>
                        <a href="#" class="footer-link">Kebijakan Privasi</a>
                        <a href="#" class="footer-link">FAQ</a>
                    </div>
                    <div>
                        <div class="footer-head">KONTAK</div>
                        <p class="footer-link footer-contact mb-2">Gedung Teknologi Informasi Lt. 3<br>Jl. Jenderal Sudirman No. 88</p>
                        <p class="footer-link footer-contact mb-0">support@sinemu.id</p>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">&copy; {{ date('Y') }} SINEMU INDONESIA Ã‚Â· BUILD FOR COMMUNITY</div>
        </div>
    </footer>
    {{-- Footer End --}}

@endsection
