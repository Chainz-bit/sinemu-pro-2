@extends('layouts.main')

@php
    $managerRoleLabelLower = \App\Support\RoleLabels::managerLower();
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
                    <span class="report-detail-chip {{ $detail->status_class }}">
                        <iconify-icon icon="mdi:shield-check-outline" aria-hidden="true"></iconify-icon>
                        {{ $detail->status_label }}
                    </span>
                    <p class="report-detail-subtitle">
                        {{ $detail->subtitle ?? ('Detail laporan barang ' . ($detail->type === 'hilang' ? 'hilang' : 'temuan') . ' untuk membantu pengguna memahami informasi sebelum tindak lanjut.') }}
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

                    @if($detail->type === 'temuan')
                        @if(!empty($detail->has_approved_claim))
                            <div class="report-detail-approved-banner alert alert-success d-flex align-items-center gap-3 p-3 my-3 rounded" role="alert" style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.35); color: #065f46;">
                                <iconify-icon icon="mdi:check-decagram" style="font-size: 2.2rem; color: #10b981; flex-shrink: 0;"></iconify-icon>
                                <div class="flex-grow-1">
                                    <strong class="d-block" style="font-size: 1.05rem; font-weight: 700; color: #047857;">Klaim Anda Disetujui!</strong>
                                    <p class="mb-0 mt-1" style="color: #065f46; font-size: 0.95rem; line-height: 1.45;">
                                        Klaim Anda untuk barang ini telah disetujui. Silakan ikuti instruksi pengambilan di bawah atau hubungi pengelola.
                                    </p>
                                </div>
                            </div>
                        @else
                            <div class="report-detail-preclaim-note" role="note" aria-label="Informasi klaim">
                                <strong>Verifikasi Kepemilikan Wajib</strong>
                                <p>{{ $detail->preclaim_note ?? 'User tidak langsung dianggap pemilik. Ajukan klaim dan lengkapi bukti kepemilikan agar dapat diverifikasi ' . $managerRoleLabelLower . '.' }}</p>
                            </div>
                        @endif
                    @endif

                    <div class="report-detail-actions">
                        <a href="{{ route('home') }}" class="btn btn-sinemu btn-sinemu-primary">
                            <iconify-icon icon="mdi:arrow-left" aria-hidden="true"></iconify-icon>
                            Lihat Laporan Lain
                        </a>
                        @if($detail->type === 'temuan')
                            @if(!empty($detail->has_approved_claim))
                                <a href="{{ $detail->claim_action_url ?? route('user.claims.show', $detail->user_claim_id) }}" class="btn btn-success d-inline-flex align-items-center gap-2 px-3 py-2 fw-semibold">
                                    <iconify-icon icon="mdi:ticket-confirmation-outline" style="font-size: 1.25rem;"></iconify-icon>
                                    Instruksi Pengambilan
                                </a>
                                <a href="{{ route('user.claim-history') }}" class="btn btn-outline-secondary">
                                    Riwayat Klaim
                                </a>
                            @elseif(($detail->is_claimable ?? false) === true)
                                @auth
                                    <a href="{{ $detail->claim_action_url ?? route('home') }}" class="btn btn-outline-primary">
                                        {{ $detail->claim_action_label ?? 'Ajukan Klaim' }}
                                    </a>
                                    <a href="{{ route('user.claim-history') }}" class="btn btn-outline-secondary">
                                        Pantau Status Klaim
                                    </a>
                                @else
                                    <a href="{{ $detail->claim_action_url ?? route('home') }}" class="btn btn-outline-primary">
                                        Masuk untuk Klaim Barang
                                    </a>
                                @endauth
                            @else
                                <a href="{{ $detail->claim_action_url ?? route('home') }}" class="btn btn-outline-secondary">
                                    {{ $detail->claim_action_label ?? 'Kembali ke Daftar Temuan' }}
                                </a>
                            @endif
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
