@extends('layouts.main')
@section('content')
    {{-- ========================================= --}}
    {{-- Main Content Container Start --}}
    {{-- ========================================= --}}
    <div class="container pb-5 home-main">
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
                    <li class="nav-item"><a class="nav-link" href="#admin-kecamatan">Admin</a></li>
                    <li class="nav-item"><a class="nav-link" href="#lokasi-pengambilan">Lokasi</a></li>
                </ul>
                <div class="d-flex align-items-center gap-3 nav-actions ms-lg-3">
                    @auth
                        <button class="icon-btn"><i class="fa-regular fa-bell"></i></button>
                        <button class="icon-btn"><i class="fa-solid fa-gear"></i></button>
                        <div class="user-chip profile-chip">
                            <div>
                                <div class="fw-semibold small">{{ $userName ?? 'Pengguna' }}</div>
                                <div class="text-muted xsmall">{{ $userLocation ?? 'Lokasi Anda' }}</div>
                            </div>
                            <span class="avatar avatar-img">
                                <img src="{{ asset('img/avatar.png') }}" alt="Profil {{ $userName ?? 'Pengguna' }}" loading="lazy" decoding="async">
                            </span>
                        </div>
                    @else
                        <a class="btn btn-sinemu btn-sinemu-primary btn-sm px-3" href="{{ url('/login') }}">Masuk</a>
                    @endauth
                </div>
            </div>
        </nav>
        {{-- Navbar End --}}

        {{-- ========================================= --}}
        {{-- Hero Start --}}
        {{-- ========================================= --}}
        <section class="section-space">
            <div class="row g-4 align-items-stretch">
                <div class="col-lg-8" data-animate="1">
                    <div class="hero-card hero-card-main h-100">
                        <h1 class="hero-title mb-3">Selamat Datang di <span class="accent">Sinemu</span></h1>
                        <p class="hero-subtitle mb-4">Platform Pencarian dan Pelaporan Barang Hilang &amp; Temuan terintegrasi. Menghubungkan kejujuran penemu dengan harapan pemilik barang.</p>
                        <div class="d-flex flex-wrap gap-3">
                            <button class="btn btn-sinemu btn-sinemu-primary" type="button" data-action="coming-soon">Melapor barang <i class="fa-solid fa-plus-circle ms-2"></i></button>
                            <button class="btn btn-sinemu btn-sinemu-outline" type="button" data-action="coming-soon">Cari Barang Saya</button>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4" data-animate="2">
                    <div class="hero-card hero-info hero-summary">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h5 fw-bold mb-0"><i class="fa-regular fa-rectangle-list me-2 text-primary"></i>Ringkasan Sinemu</h2>
                        </div>
                        <div class="hero-info-card mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-bold text-uppercase small text-primary">Barang Kembali</div>
                                <span class="badge rounded-pill text-bg-primary px-3">92%</span>
                            </div>
                            <div class="text-muted small">Lebih dari 1,200 barang telah dikembalikan ke pemilik sah melalui platform ini.</div>
                        </div>
                        <div class="hero-info-card mb-3">
                            <div class="fw-bold small text-primary">Privasi &amp; Keamanan</div>
                            <div class="text-muted small">Verifikasi berlapis untuk menjamin keamanan klaim setiap barang yang ditemukan.</div>
                        </div>
                        <div class="hero-info-card">
                            <div class="fw-bold small text-primary">Titik Distribusi</div>
                            <div class="text-muted small">Bekerja sama dengan 24 kantor kecamatan sebagai lokasi resmi pengambilan barang.</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        {{-- Hero End --}}

        {{-- ========================================= --}}
        {{-- Filter Start --}}
        {{-- ========================================= --}}
        <section id="pencarian" class="section-space pt-0">
            <div class="surface-card filter-wrap" data-animate="1">
                <div class="filter-header">
                    <div class="filter-head-left">
                        <span class="filter-icon">
                            <img src="{{ asset('img/icon filter.png') }}" alt="Filter" class="filter-icon-img" loading="lazy" decoding="async">
                        </span>
                        <div>
                            <p class="filter-title mb-1">Filter Pencarian Cepat</p>
                            <p class="filter-subtitle">Saring hasil pencarian berdasarkan kategori, waktu, dan lokasi secara spesifik.</p>
                        </div>
                    </div>
                    <button class="chevron-btn" type="button"><i class="fa-solid fa-chevron-up"></i></button>
                </div>

                <form id="filterForm" class="row g-3" novalidate>
                    <div class="col-md-6 col-xl-3">
                        <label for="keywordInput" class="form-label">KATA KUNCI</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input id="keywordInput" type="text" class="form-control" placeholder="Dompet, Kunci, HP...">
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <label for="categorySelect" class="form-label">KATEGORI</label>
                        <select id="categorySelect" class="form-select">
                            @foreach ($categories as $category)
                                <option value="{{ $category }}">{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <label for="dateInput" class="form-label">WAKTU PENEMUAN</label>
                        <input id="dateInput" type="date" class="form-control" placeholder="mm/dd/yyyy">
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <label for="regionSelect" class="form-label">WILAYAH</label>
                        <select id="regionSelect" class="form-select">
                            @foreach ($regions as $region)
                                <option value="{{ $region }}">{{ $region }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-3 align-items-center">
                        <button id="searchButton" class="btn btn-sinemu btn-sinemu-primary" type="submit">CARI</button>
                        <span class="muted-copy small">Filter tanggal dan wilayah berfungsi untuk data contoh yang ditampilkan di halaman ini.</span>
                    </div>
                </form>
            </div>
        </section>
        {{-- Filter End --}}

        {{-- ========================================= --}}
        {{-- Daftar Barang Start --}}
        {{-- ========================================= --}}
        <section id="hilang-temuan" class="section-space pt-0">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="surface-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <div class="mini-kicker mb-2">Daftar Barang Hilang</div>
                                <h2 class="h3 fw-bold mb-0">Daftar Barang Hilang</h2>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span id="lostCountText" class="text-muted small">{{ count($lostItems) }} item</span>
                                <button type="button" class="carousel-nav-btn" data-carousel-target="lostItemsList" data-carousel-dir="prev" aria-label="Sebelumnya">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </button>
                                <button type="button" class="carousel-nav-btn" data-carousel-target="lostItemsList" data-carousel-dir="next" aria-label="Berikutnya">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        <div class="carousel-track" id="lostItemsList">
                            @foreach ($lostItems as $item)
                                <div class="carousel-item filter-item"
                                    data-list="lost"
                                    data-category="{{ strtoupper($item['category']) }}"
                                    data-name="{{ strtolower($item['name']) }}"
                                    data-region="{{ strtolower($item['location']) }}"
                                    data-date="{{ $item['date'] }}">
                                    <div class="item-card item-card-lost">
                                        <div class="item-visual gradient-blue">
                                            <span class="item-badge">{{ $item['category'] }}</span>
                                            <span class="status-pill status-lost">HILANG</span>
                                        </div>
                                        <div class="mt-3">
                                            <h3 class="h6 fw-bold mb-1">{{ $item['name'] }}</h3>
                                            <p class="text-muted small mb-2"><i class="fa-solid fa-location-dot me-1 text-secondary"></i>{{ $item['location'] }}</p>
                                            <p class="text-muted xsmall mb-3">{{ $item['date'] }}</p>
                                            <button class="btn btn-dark btn-sm rounded-pill px-3 detail-button" type="button" data-item="{{ $item['name'] }}" data-category="{{ $item['category'] }}" data-list="Barang Hilang">LIHAT DETAIL</button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div id="lostEmptyState" class="empty-state mt-3">Tidak ada barang hilang yang cocok dengan filter saat ini.</div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="surface-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <div class="mini-kicker mb-2">Daftar Barang Temuan</div>
                                <h2 class="h3 fw-bold mb-0">Daftar Barang Temuan</h2>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span id="foundCountText" class="text-muted small">{{ count($foundItems) }} item</span>
                                <button type="button" class="carousel-nav-btn" data-carousel-target="foundItemsList" data-carousel-dir="prev" aria-label="Sebelumnya">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </button>
                                <button type="button" class="carousel-nav-btn" data-carousel-target="foundItemsList" data-carousel-dir="next" aria-label="Berikutnya">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        <div class="carousel-track" id="foundItemsList">
                            @foreach ($foundItems as $item)
                                <div class="carousel-item filter-item"
                                    data-list="found"
                                    data-category="{{ strtoupper($item['category']) }}"
                                    data-name="{{ strtolower($item['name']) }}"
                                    data-region="{{ strtolower($item['location']) }}"
                                    data-date="{{ $item['date'] }}">
                                    <div class="item-card item-card-found">
                                        <div class="item-visual gradient-gold">
                                            <span class="item-badge">{{ $item['category'] }}</span>
                                            <span class="status-pill status-found">TEMUAN</span>
                                        </div>
                                        <div class="mt-3">
                                            <h3 class="h6 fw-bold mb-1">{{ $item['name'] }}</h3>
                                            <p class="text-muted small mb-2"><i class="fa-solid fa-location-dot me-1 text-secondary"></i>{{ $item['location'] }}</p>
                                            <p class="text-muted xsmall mb-3">{{ $item['date'] }}</p>
                                            <button class="btn btn-dark btn-sm rounded-pill px-3 detail-button" type="button" data-item="{{ $item['name'] }}" data-category="{{ $item['category'] }}" data-list="Barang Temuan">KLAIM BARANG</button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div id="foundEmptyState" class="empty-state mt-3">Tidak ada barang temuan yang cocok dengan filter saat ini.</div>
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
                                <p class="text-muted mb-0">Cari barang Anda di daftar temuan dan catat kode unik barang untuk proses verifikasi lebih cepat oleh sistem kami.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="claim-item">
                            <div class="step-number">2</div>
                            <div>
                                <h3 class="h5 fw-bold mb-2">Unggah Bukti Sah</h3>
                                <p class="text-muted mb-0">Siapkan foto barang saat masih dimiliki, kuitansi pembelian, atau jelaskan ciri fisik mendetail yang tidak terlihat di foto.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="claim-item">
                            <div class="step-number">3</div>
                            <div>
                                <h3 class="h5 fw-bold mb-2">Verifikasi Admin</h3>
                                <p class="text-muted mb-0">Tim admin kami akan memproses klaim Anda dalam waktu 1x24 jam untuk memastikan keabsahan kepemilikan barang.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="claim-item">
                            <div class="step-number">4</div>
                            <div>
                                <h3 class="h5 fw-bold mb-2">Pengambilan Fisik</h3>
                                <p class="text-muted mb-0">Datang ke lokasi kantor kecamatan yang telah ditentukan dengan membawa kartu identitas asli (KTP/SIM) sebagai bukti akhir.</p>
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
        {{-- Pendaftaran + Peta Start --}}
        {{-- ========================================= --}}
        <section id="pendaftaran" class="section-space pt-0">
            <div id="admin-kecamatan" class="admin-register-card p-4 p-lg-5 mb-4">
                <div class="text-center mb-4">
                    <h2 class="section-title mb-2">Pendaftaran Admin Kecamatan</h2>
                    <p class="section-subtitle mx-auto mb-0">Khusus untuk perwakilan resmi kantor kecamatan di seluruh wilayah.</p>
                </div>
                <div class="row g-3 justify-content-center register-form">
                    <div class="col-md-6">
                        <label class="register-label">NAMA INSTANSI KECAMATAN</label>
                        <input id="adminOfficeName" type="text" class="form-control register-input" placeholder="Kecamatan Suka Makmur...">
                    </div>
                    <div class="col-md-6">
                        <label class="register-label">KONTAK WHATSAPP</label>
                        <input id="adminPhone" type="text" class="form-control register-input" placeholder="0812-xxxx-xxxx">
                    </div>
                    <div class="col-md-6">
                        <label for="adminRegionSelect" class="register-label">WILAYAH KECAMATAN</label>
                        <select id="adminRegionSelect" class="form-select register-input">
                            @foreach ($mapRegions as $region)
                                <option value="{{ $region['slug'] }}">{{ $region['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="register-label">ALAMAT KANTOR LENGKAP</label>
                        <textarea id="adminAddress" class="form-control register-textarea" rows="2">Jl. Pahlawan No. 123, Kelurahan Bahagia...</textarea>
                    </div>
                    <div class="col-12 mt-2">
                        <button class="btn btn-register-submit w-100" type="button" data-action="registration">AJUKAN PENDAFTARAN &gt;</button>
                    </div>
                    <div class="col-12 mt-3">
                        <div class="register-note">
                            <i class="fa-regular fa-shield me-2"></i>
                            Setiap pengajuan akun admin akan melalui proses verifikasi fisik dan administratif langsung ke lokasi kantor kecamatan terkait untuk menjamin keamanan dan keaslian data titik pengambilan barang.
                        </div>
                    </div>
                </div>
            </div>

            <div id="lokasi-pengambilan" class="map-card p-4 p-lg-5 map-modern">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
                    <div>
                        <h2 class="section-title mb-2">Peta Lokasi Pengambilan</h2>
                        <p class="section-subtitle mb-0">Navigasi titik penyerahan dan pengambilan barang resmi di seluruh wilayah kota.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn map-btn-near" type="button" data-action="coming-soon"><i class="fa-solid fa-location-crosshairs me-2"></i>CARI TERDEKAT</button>
                    </div>
                </div>

                <div class="map-layout">
                    <form id="mapFilterForm" class="map-sidebar" novalidate>
                        <div class="map-label">FILTER WILAYAH</div>
                        <select id="mapRegionSelect" class="map-select-input">
                            @foreach ($mapRegions as $region)
                                <option value="{{ $region['slug'] }}">{{ $region['name'] }}</option>
                            @endforeach
                        </select>

                        <div class="map-label mt-4">TIPE TITIK LAYANAN</div>
                        <label class="map-radio active">
                            <input class="map-service-input" type="radio" name="serviceType" value="kecamatan" checked>
                            <span class="dot"></span>KANTOR KECAMATAN
                        </label>
                        <label class="map-radio">
                            <input class="map-service-input" type="radio" name="serviceType" value="polisi">
                            <span class="dot"></span>KANTOR POLISI
                        </label>
                        <label class="map-radio">
                            <input class="map-service-input" type="radio" name="serviceType" value="keamanan">
                            <span class="dot"></span>POS KEAMANAN
                        </label>

                        <div class="map-label mt-4">NAVIGASI CEPAT</div>
                        <div class="map-quick-grid">
                            @foreach ($mapRegions as $index => $region)
                                <button class="map-chip{{ $index === 0 ? ' active' : '' }}" type="button" data-map-region="{{ $region['slug'] }}">{{ strtoupper(str_replace('Kecamatan ', '', $region['name'])) }}</button>
                            @endforeach
                        </div>
                    </form>

                    <div class="map-board">
                        <div class="map-canvas">
                            <div id="pickupMap" class="real-map" aria-label="Peta lokasi pengambilan"></div>
                            <div class="map-smart-nav">
                                <div class="map-smart-icon"><i class="fa-regular fa-map"></i></div>
                                <div>
                                    <div class="map-smart-title">NAVIGASI PINTAR</div>
                                    <div class="map-smart-text">Wilayah aktif: <span id="activeRegionName">{{ $mapRegions[0]['name'] ?? '-' }}</span>. Peta akan disesuaikan otomatis berdasarkan data pendaftaran admin kecamatan.</div>
                                </div>
                            </div>
                            <div class="map-zoom">
                                <button id="mapZoomIn" type="button" class="map-ctrl-btn" title="Perbesar">+</button>
                                <button id="mapZoomOut" type="button" class="map-ctrl-btn" title="Perkecil">-</button>
                                <a id="openGoogleMaps" href="https://www.google.com/maps" target="_blank" rel="noopener noreferrer" class="map-open-link" title="Buka Google Maps">
                                    <i class="fa-solid fa-location-arrow"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <script id="mapRegionData" type="application/json">@json($mapRegions)</script>
        </section>
        {{-- Pendaftaran + Peta End --}}

        @push('styles')
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
        @endpush

        @push('scripts')
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
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
