@extends('super.layouts.app')

@php
    $managerRoleLabel = \App\Support\RoleLabels::manager();
    $pageTitle = 'Detail ' . $managerRoleLabel . ' - Super Admin';
    $activeMenu = 'admins';
    $searchAction = route('super.admins.index');
    $searchPlaceholder = 'Cari nama pengelola, instansi, atau kecamatan';
    $statusKey = \App\Support\AdminVerificationStatusPresenter::key($admin->status_verifikasi);
    $statusLabel = \App\Support\AdminVerificationStatusPresenter::label($statusKey);
    $displayName = trim((string) ($admin->nama ?: $admin->username ?: 'Pengelola'));
    $initial = mb_strtoupper(mb_substr($displayName, 0, 1));
    $instansiLabel = $admin->instansi ?: 'Instansi belum diisi';
    $kecamatanLabel = $admin->kecamatan ?: 'Kecamatan belum diisi';
    $identityMeta = collect([$admin->email, $admin->username ? '@' . $admin->username : null])->filter()->implode(' | ');
    $detailSections = [
        [
            'title' => 'Informasi Pribadi',
            'items' => [
                ['label' => 'Nama Lengkap', 'value' => $admin->nama ?: '-'],
                ['label' => 'Email', 'value' => $admin->email ?: '-'],
                ['label' => 'Nomor HP', 'value' => $admin->nomor_telepon ?: '-'],
            ],
        ],
        [
            'title' => 'Informasi Akun',
            'items' => [
                ['label' => 'Username', 'value' => $admin->username ?: '-'],
                ['label' => 'Status Akun', 'value' => $statusLabel],
                ['label' => 'Tanggal Daftar', 'value' => optional($admin->created_at)->format('d M Y H:i') ? optional($admin->created_at)->format('d M Y H:i') . ' WIB' : '-'],
            ],
        ],
        [
            'title' => 'Informasi Wilayah',
            'items' => [
                ['label' => 'Instansi', 'value' => $admin->instansi ?: '-'],
                ['label' => 'Kecamatan', 'value' => $admin->kecamatan ?: '-'],
                ['label' => 'Alamat/Wilayah Tugas', 'value' => $admin->alamat_lengkap ?: '-'],
            ],
        ],
        [
            'title' => 'Status & Verifikasi',
            'items' => [
                ['label' => 'Terakhir Diperbarui', 'value' => optional($admin->updated_at)->format('d M Y H:i') ? optional($admin->updated_at)->format('d M Y H:i') . ' WIB' : '-'],
                ['label' => 'Diverifikasi Pada', 'value' => optional($admin->verified_at)->format('d M Y H:i') ? optional($admin->verified_at)->format('d M Y H:i') . ' WIB' : '-'],
                ['label' => 'Catatan Penolakan/Revisi', 'value' => $admin->alasan_penolakan ?: '-'],
            ],
        ],
    ];
@endphp

@section('page-content')
    <div class="dashboard-page-content super-page-content super-manager-detail-page">
        <section class="intro">
            <h1>Detail Akun Pengelola</h1>
            <p>Lihat data lengkap akun pengelola barang, status verifikasi, dan informasi kontak.</p>
        </section>

        <section class="admin-detail-card" aria-labelledby="admin-detail-title">
            <div class="admin-detail-hero">
                <div class="admin-detail-identity">
                    <div class="admin-detail-avatar" aria-hidden="true">{{ $initial }}</div>
                    <div class="admin-detail-heading">
                        <h2 id="admin-detail-title" class="admin-detail-title">{{ $displayName }}</h2>
                        <p class="admin-detail-subtitle">{{ $instansiLabel }} | {{ $kecamatanLabel }}</p>
                        @if($identityMeta !== '')
                            <p class="admin-detail-meta">{{ $identityMeta }}</p>
                        @endif
                    </div>
                </div>
                <span class="status-chip admin-detail-status {{ \App\Support\AdminVerificationStatusPresenter::badgeClass($statusKey) }}">
                    {{ $statusLabel }}
                </span>
            </div>

            <div class="admin-detail-body">
                <div class="admin-detail-section-grid">
                    @foreach($detailSections as $section)
                        <section class="admin-detail-section">
                            <h3 class="admin-detail-section-title">{{ $section['title'] }}</h3>
                            <div class="admin-detail-field-grid">
                                @foreach($section['items'] as $item)
                                    <div class="admin-detail-field">
                                        <span class="admin-detail-label">{{ $item['label'] }}</span>
                                        <strong class="admin-detail-value">{{ $item['value'] }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
            </div>

            <div class="admin-detail-actions">
                <a href="{{ route('super.admins.index') }}" class="super-inline-btn">Kembali ke Daftar</a>
                <a href="{{ route('super.admins.edit', $admin) }}" class="super-inline-btn is-accept">Edit Akun</a>
                <form method="POST"
                    action="{{ route('super.admins.destroy', $admin) }}"
                    data-confirm-delete
                    data-confirm-title="Hapus Akun Pengelola?"
                    data-confirm-message="Akun pengelola ini akan dihapus dari sistem. Tindakan ini tidak dapat dibatalkan. Nama: {{ $admin->nama }}"
                    data-confirm-submit-label="Ya, Hapus Akun"
                    data-confirm-submit-variant="danger">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="super-inline-btn is-reject">Hapus Akun</button>
                </form>
            </div>
        </section>
    </div>
@endsection
