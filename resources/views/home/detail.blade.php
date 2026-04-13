@extends('layouts.main')

@php
    $title = $pageTitle ?? 'Detail Laporan - SiNemu';
@endphp

@section('content')
    <div class="report-detail-page">
        <div class="report-detail-stage">
            <section class="report-detail-hero">
                <div class="report-detail-media">
                    <img src="{{ $detail->image_url }}" alt="{{ $detail->title }}" loading="lazy" decoding="async">
                </div>

                <div class="report-detail-main">
                    <h1>{{ $detail->title }}</h1>
                    <span class="report-detail-chip {{ $detail->status_class }}">{{ $detail->status_label }}</span>
                    <p class="report-detail-subtitle">
                        Detail laporan barang {{ $detail->type === 'hilang' ? 'hilang' : 'temuan' }} untuk membantu pengguna memahami informasi sebelum tindak lanjut.
                    </p>

                    <div class="report-detail-meta-grid">
                        <article>
                            <span class="report-detail-meta-label">
                                <iconify-icon icon="mdi:tag-outline"></iconify-icon>
                                Kategori
                            </span>
                            <strong>{{ $detail->category }}</strong>
                        </article>
                        <article>
                            <span class="report-detail-meta-label">
                                <iconify-icon icon="mdi:map-marker-outline"></iconify-icon>
                                Lokasi
                            </span>
                            <strong>{{ $detail->location }}</strong>
                        </article>
                        <article>
                            <span class="report-detail-meta-label">
                                <iconify-icon icon="mdi:calendar-month-outline"></iconify-icon>
                                Tanggal Laporan
                            </span>
                            <strong>{{ $detail->date_label }}</strong>
                        </article>
                        <article>
                            <span class="report-detail-meta-label">
                                <iconify-icon icon="mdi:account-outline"></iconify-icon>
                                Pelapor/Penanggung Jawab
                            </span>
                            <strong>{{ $detail->reporter }}</strong>
                        </article>
                    </div>

                    <div class="report-detail-description">
                        <h2>Deskripsi</h2>
                        <p>{{ $detail->description }}</p>
                    </div>

                    <div class="report-detail-actions">
                        <a href="{{ route('home') }}#hilang-temuan" class="btn btn-sinemu btn-sinemu-primary">
                            Lihat Laporan Lain
                        </a>
                        @if($detail->type === 'temuan')
                            @auth
                                <a href="{{ route('home') }}#hilang-temuan" class="btn btn-outline-primary">
                                    Kembali untuk Klaim Barang
                                </a>
                            @else
                                <a href="{{ route('home') }}" class="btn btn-outline-primary">
                                    Masuk untuk Klaim Barang
                                </a>
                            @endauth
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
