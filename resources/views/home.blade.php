@extends('layouts.main')
@section('content')
    {{-- ========================================= --}}
    {{-- Main Content Container Start --}}
    {{-- ========================================= --}}
    <div class="container-fluid px-0 pb-5 home-main">
        {{-- ========================================= --}}
        {{-- Navbar Start --}}
        {{-- ========================================= --}}
        <nav class="navbar navbar-expand-lg floating-nav fixed-nav px-3 px-lg-4" id="mainNavBar">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="{{ route('home') }}">
                <img src="{{ asset('img/logo.png') }}" alt="Sinemu" class="brand-logo" width="160" height="50" fetchpriority="high">
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav mx-auto mb-3 mb-lg-0 gap-lg-4 nav-centered">
                    <li class="nav-item"><a class="nav-link" href="#pencarian">Pencarian</a></li>
                    <li class="nav-item"><a class="nav-link" href="#hilang-temuan">Hilang &amp; Temuan</a></li>
                    <li class="nav-item"><a class="nav-link" href="#klaim">Tutorial</a></li>
                    <li class="nav-item"><a class="nav-link" href="#lokasi-pengambilan">Lokasi</a></li>
                    <li class="nav-item"><a class="nav-link" href="#kontak">Kontak</a></li>
                </ul>
                <div class="d-flex align-items-center gap-3 nav-actions ms-lg-3">
                    @auth
                        <button class="icon-btn"><i class="fa-regular fa-bell"></i></button>
                        <button class="icon-btn"><i class="fa-solid fa-gear"></i></button>
                        <div class="dropdown profile-dropdown">
                            <button class="user-chip profile-chip profile-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <div>
                                    <div class="fw-semibold small">{{ $userName ?? 'Pengguna' }}</div>
                                    <div class="text-muted xsmall">{{ $userLocation ?? 'Lokasi Anda' }}</div>
                                </div>
                                <span class="avatar avatar-img">
                                    <img src="{{ asset('img/profil.jpg') }}" alt="Profil {{ $userName ?? 'Pengguna' }}" loading="lazy" decoding="async">
                                </span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end profile-menu">
                                <li>
                                    <a class="dropdown-item" href="{{ route('dashboard') }}">
                                        <i class="fa-solid fa-gauge-high me-2"></i>Dashboard
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @else
                        <button class="btn btn-sinemu btn-sinemu-primary btn-sm px-3 py-2" type="button" data-bs-toggle="modal" data-bs-target="#loginPortalModal">
                            Masuk
                        </button>
                    @endauth
                </div>
            </div>
        </nav>
        {{-- Navbar End --}}

        {{-- ========================================= --}}
        {{-- Login Portal Modal Start --}}
        {{-- ========================================= --}}
        @guest
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

                            <a href="{{ route('admin.login') }}" class="login-portal-option">
                                <span class="login-portal-option-icon"><i class="fa-regular fa-id-badge"></i></span>
                                <span class="login-portal-option-text">
                                    <strong>Admin</strong>
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
        @auth
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

        <section id="pencarian" class="section-space hero-modern-section">
            <div class="hero-modern text-center" data-animate="1">
                <h1 class="hero-modern-title mb-3">Kehilangan atau Menemukan <span>Barang</span> di <span>Indramayu?</span></h1>
                <p class="hero-modern-subtitle mb-4">SiNemu bantu temukan barang berhargamu. Lapor dengan mudah, cari dengan cepat, dan klaim dengan aman.</p>
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
                            <input id="keywordInput" type="text" class="form-control" placeholder="Dompet, Kunci, HP...">
                        </div>
                    </div>
                    <div class="col-md-6 col-xl">
                        <label for="categoryDropdownToggle" class="form-label ps-2 py-2">KATEGORI</label>
                        <div class="filter-dropdown" id="categoryDropdown">
                            <button id="categoryDropdownToggle" class="form-select text-start filter-dropdown-toggle" type="button" aria-expanded="false">
                                {{ data_get($categories, 0, 'Semua Kategori') }}
                            </button>
                            <ul id="categoryDropdownMenu" class="filter-dropdown-menu" role="listbox">
                                @foreach ($categories as $category)
                                    <li>
                                        <button type="button" class="filter-option" data-value="{{ $category }}">{{ $category }}</button>
                                    </li>
                                @endforeach
                            </ul>
                            <select id="categorySelect" class="d-none">
                                @foreach ($categories as $category)
                                    <option value="{{ $category }}">{{ $category }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl">
                        <label for="dateInput" class="form-label ps-2 py-2">WAKTU PENEMUAN</label>
                        <input id="dateInput" type="text" class="form-control modern-date-input" placeholder="dd/mm/yyyy" inputmode="numeric" autocomplete="off">
                    </div>
                    <div class="col-md-6 col-xl">
                        <label for="regionDropdownToggle" class="form-label ps-2 py-2">WILAYAH</label>
                        <div class="filter-dropdown" id="regionDropdown">
                            <button id="regionDropdownToggle" class="form-select text-start filter-dropdown-toggle" type="button" aria-expanded="false">
                                {{ data_get($regions, 0, 'Seluruh Wilayah') }}
                            </button>
                            <ul id="regionDropdownMenu" class="filter-dropdown-menu" role="listbox">
                                @foreach ($regions as $region)
                                    <li>
                                        <button type="button" class="filter-option" data-value="{{ $region }}">{{ $region }}</button>
                                    </li>
                                @endforeach
                            </ul>
                            <select id="regionSelect" class="d-none filter-region-select">
                                @foreach ($regions as $region)
                                    <option value="{{ $region }}">{{ $region }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-2 d-flex">
                        <button id="searchButton" class="btn btn-sinemu btn-sinemu-primary w-100 filter-search-btn" type="submit">
                            <i class="fa-solid fa-magnifying-glass me-2"></i>Cari
                        </button>
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
                        <h2 class="item-block-title mb-0">Daftar Barang Hilang Terkini</h2>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        @auth
                            <button type="button" class="btn btn-sinemu btn-sinemu-primary btn-sm" data-action="open-lost-report">
                                Lapor Barang Hilang
                            </button>
                        @endauth
                        <a href="#" class="item-link-more">Lihat Semua <i class="fa-solid fa-chevron-right"></i></a>
                        <button type="button" class="carousel-nav-btn" data-carousel-target="lostItemsList" data-carousel-dir="prev" aria-label="Sebelumnya">
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>
                        <button type="button" class="carousel-nav-btn" data-carousel-target="lostItemsList" data-carousel-dir="next" aria-label="Berikutnya">
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    </div>
                </div>

                <div class="carousel-track carousel-draggable" id="lostItemsList">
                    @forelse ($lostItems as $item)
                        <article class="carousel-item item-card-v2 filter-item"
                            data-list="lost"
                            data-category="{{ strtoupper($item['category']) }}"
                            data-name="{{ strtolower($item['name']) }}"
                            data-region="{{ strtolower($item['location']) }}"
                            data-date="{{ $item['date'] }}">
                            <div class="item-media">
                                <img src="{{ $item['image_url'] ?? asset('img/login-image.png') }}" alt="{{ $item['name'] }}" loading="lazy" decoding="async" width="600" height="360">
                                <span class="item-status item-status-danger">Belum Ditemukan</span>
                            </div>
                            <div class="item-body">
                                <h3 class="item-name">{{ $item['name'] }}</h3>
                                <p class="item-meta"><i class="fa-solid fa-location-dot"></i> {{ $item['location'] }}</p>
                                <p class="item-meta"><i class="fa-regular fa-clock"></i> {{ $item['date_label'] ?? $item['date'] }}</p>
                                <a href="{{ $item['detail_url'] }}" class="btn item-action-btn">Lihat Detail Laporan</a>
                            </div>
                        </article>
                    @empty
                    @endforelse
                </div>
                <div id="lostEmptyState" class="empty-state mt-3">Tidak ada barang hilang yang cocok dengan filter saat ini.</div>
            </div>

            <div class="item-block surface-card p-3 p-lg-4">
                <div class="item-block-head mb-3">
                    <div>
                        <div class="item-kicker item-kicker-success">Update Terbaru</div>
                        <h2 class="item-block-title mb-0">Barang Temuan Terbaru</h2>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        @auth
                            <button type="button" class="btn btn-sinemu btn-sinemu-primary btn-sm" data-action="open-found-report">
                                Lapor Barang Temuan
                            </button>
                        @endauth
                        <span class="item-chip item-chip-active">Terbaru</span>
                        <span class="item-chip">Terverifikasi</span>
                        <button type="button" class="carousel-nav-btn" data-carousel-target="foundItemsList" data-carousel-dir="prev" aria-label="Sebelumnya">
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>
                        <button type="button" class="carousel-nav-btn" data-carousel-target="foundItemsList" data-carousel-dir="next" aria-label="Berikutnya">
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    </div>
                </div>

                <div class="carousel-track carousel-draggable" id="foundItemsList">
                    @forelse ($foundItems as $item)
                        <article class="carousel-item item-card-v2 filter-item"
                            data-list="found"
                            data-category="{{ strtoupper($item['category']) }}"
                            data-name="{{ strtolower($item['name']) }}"
                            data-region="{{ strtolower($item['location']) }}"
                            data-date="{{ $item['date'] }}">
                            <div class="item-media">
                                <img src="{{ $item['image_url'] ?? asset('img/login-image.png') }}" alt="{{ $item['name'] }}" loading="lazy" decoding="async" width="600" height="360">
                                <span class="item-status item-status-success">Sudah Ditemukan</span>
                            </div>
                            <div class="item-body">
                                <h3 class="item-name">{{ $item['name'] }}</h3>
                                <p class="item-meta"><i class="fa-solid fa-location-dot"></i> {{ $item['location'] }}</p>
                                <p class="item-meta"><i class="fa-regular fa-clock"></i> {{ $item['date_label'] ?? $item['date'] }}</p>
                                <a href="{{ $item['detail_url'] }}" class="item-detail-link">Lihat Detail Laporan</a>
                                @auth
                                    <button
                                        class="btn item-action-btn claim-button"
                                        type="button"
                                        data-barang-id="{{ $item['id'] }}"
                                        data-barang-name="{{ $item['name'] }}"
                                    >
                                        Klaim Barang Ini
                                    </button>
                                @else
                                    <button class="btn item-action-btn" type="button" data-bs-toggle="modal" data-bs-target="#loginPortalModal">Klaim Barang Ini</button>
                                @endauth
                            </div>
                        </article>
                    @empty
                    @endforelse
                </div>
                <div id="foundEmptyState" class="empty-state mt-3">Tidak ada barang temuan yang cocok dengan filter saat ini.</div>
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
                                <h3 class="h5 fw-bold mb-2">Verifikasi Admin</h3>
                                <p class="text-muted fs-6 mb-0">Tim admin kami akan memproses klaim Anda dalam waktu 1x24 jam untuk memastikan keabsahan kepemilikan barang.</p>
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
                <div class="claim-help mt-4 text-center">
                    <a href="#" class="claim-help-link">Butuh bantuan lebih lanjut dalam proses klaim? Hubungi Support Center Kami</a>
                </div>
            </div>
        </section>
        {{-- Prosedur Klaim End --}}

        {{-- ========================================= --}}
        {{-- Lokasi Pengambilan Start --}}
        {{-- ========================================= --}}
        <section id="lokasi-pengambilan" class="section-space pt-0">
            <div class="surface-card lokasi-wrap p-3 p-lg-4">
                <header class="lokasi-header">
                    <h2 class="lokasi-main-title mb-1">Lokasi Pengambilan Sinemu - Indramayu</h2>
                    <p class="lokasi-main-subtitle mb-0">Butuh bantuan klaim? Hubungi Support Center kami.</p>
                </header>

                <div class="lokasi-layout">
                    <div class="lokasi-map-panel">
                        <div id="pickupMap" class="lokasi-map-frame" aria-label="Peta lokasi pengambilan Sinemu Indramayu"></div>
                        <div class="lokasi-map-footer">
                            <span>Shortcut: Geser untuk pindah peta, pinch/scroll untuk zoom.</span>
                            <span>&copy; Sinemu Indramayu</span>
                        </div>
                    </div>

                    <aside class="lokasi-info-panel">
                        <h3 class="lokasi-title mb-2">Support & Lokasi Aktif</h3>
                        <p class="lokasi-subtitle mb-3">Pilih lokasi pengambilan terdekat, lalu buka peta atau dapatkan rute langsung.</p>

                        <div class="lokasi-contact-chip mb-3">
                            <span class="lokasi-chip-icon"><i class="fa-solid fa-location-dot"></i></span>
                            <div>
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
                        </div>

                        <a
                            class="lokasi-support-link"
                            href="https://wa.me/6281234567890?text=Halo%20Sinemu%20Support%20Indramayu%2C%20saya%20butuh%20bantuan%20proses%20klaim."
                            target="_blank"
                            rel="noopener"
                        >
                            <i class="fa-brands fa-whatsapp me-2"></i>Hubungi Support Center (0812-3456-7890)
                        </a>
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
                            <div class="col-6">
                                <article class="contact-info-card h-100">
                                    <span class="contact-info-icon bg-email"><i class="fa-regular fa-envelope"></i></span>
                                    <p class="contact-info-label">EMAIL</p>
                                    <p class="contact-info-text mb-0">support@sinemu.id</p>
                                </article>
                            </div>
                            <div class="col-6">
                                <article class="contact-info-card h-100">
                                    <span class="contact-info-icon bg-phone"><i class="fa-solid fa-phone"></i></span>
                                    <p class="contact-info-label">TELEPON</p>
                                    <p class="contact-info-text mb-0">+62 21 1234 5678</p>
                                </article>
                            </div>
                            <div class="col-6">
                                <article class="contact-info-card h-100">
                                    <span class="contact-info-icon bg-location"><i class="fa-solid fa-location-dot"></i></span>
                                    <p class="contact-info-label">ALAMAT KANTOR</p>
                                    <p class="contact-info-text mb-0">Jl. Jenderal Sudirman No. 88, Indramayu</p>
                                </article>
                            </div>
                            <div class="col-6">
                                <article class="contact-info-card h-100">
                                    <span class="contact-info-icon bg-clock"><i class="fa-regular fa-clock"></i></span>
                                    <p class="contact-info-label">JAM OPERASIONAL</p>
                                    <p class="contact-info-text mb-0">Senin - Jumat<br>08.00 - 16.00</p>
                                </article>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <form id="contactForm" class="contact-form-card h-100" novalidate>
                            <div class="mb-3">
                                <label for="contactName" class="form-label">Nama Lengkap</label>
                                <input id="contactName" type="text" class="form-control" placeholder="Masukkan nama">
                            </div>
                            <div class="mb-3">
                                <label for="contactEmail" class="form-label">Alamat Email</label>
                                <input id="contactEmail" type="email" class="form-control" placeholder="Masukkan email">
                            </div>
                            <div class="mb-3">
                                <label for="contactPhone" class="form-label">Telepon</label>
                                <input id="contactPhone" type="text" class="form-control" placeholder="Masukkan nomor telepon">
                            </div>
                            <div class="mb-3">
                                <label for="contactMessage" class="form-label">Pesan</label>
                                <textarea id="contactMessage" class="form-control contact-textarea" rows="3" placeholder="Tuliskan pesan Anda"></textarea>
                            </div>
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
            <link
                rel="stylesheet"
                href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
                integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
                crossorigin=""
            >
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        @endpush

        @auth
            <div class="modal fade" id="lostReportModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('user.lost-reports.store') }}" class="modal-content">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Lapor Barang Hilang</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Nama Barang</label>
                                <input type="text" name="nama_barang" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Lokasi Hilang</label>
                                <input type="text" name="lokasi_hilang" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tanggal Hilang</label>
                                <input type="date" name="tanggal_hilang" class="form-control" required>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Keterangan</label>
                                <textarea name="keterangan" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Kirim Laporan</button>
                        </div>
                    </form>
                </div>
            </div>

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
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Kirim Temuan</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal fade" id="claimModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('user.claims.store') }}" class="modal-content">
                        @csrf
                        <input type="hidden" name="barang_id" id="claimBarangId">
                        <div class="modal-header">
                            <h5 class="modal-title">Ajukan Klaim Barang</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Barang Temuan</label>
                                <input type="text" id="claimBarangName" class="form-control" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nama Barang Hilang Anda</label>
                                <input type="text" name="nama_barang_hilang" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Lokasi Hilang</label>
                                <input type="text" name="lokasi_hilang" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tanggal Hilang</label>
                                <input type="date" name="tanggal_hilang" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Keterangan Barang</label>
                                <textarea name="keterangan" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Catatan Klaim</label>
                                <textarea name="catatan" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Ajukan Klaim</button>
                        </div>
                    </form>
                </div>
            </div>
        @endauth

        <script id="pickupLocationsData" type="application/json">
            @json($pickupLocations ?? [])
        </script>

        @push('scripts')
            <script
                src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
                crossorigin=""
            ></script>
            <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
            <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>
        @endpush
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
            <div class="footer-bottom">&copy; {{ date('Y') }} SINEMU INDONESIA · BUILD FOR COMMUNITY</div>
        </div>
    </footer>
    {{-- Footer End --}}
@endsection
