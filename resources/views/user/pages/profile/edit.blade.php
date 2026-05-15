@extends('user.layouts.app')

@php
    $pageTitle = 'Edit Profil - User - SiNemu';
    $activeMenu = 'profile';
    $hideSearch = true;
    $hideSidebar = true;
    $topbarBackUrl = route('user.profile');
    $topbarBackLabel = 'Kembali ke Profil';
@endphp

@section('page-content')
    <div class="profile-page-content profile-page-content-edit">
        <section class="profile-account-card">
            <div class="profile-account-top">
                <div class="profile-account-main">
                    <button type="button" id="profilePhotoTrigger" class="profile-avatar-trigger" aria-label="Ganti foto profil">
                        <img id="profilePhotoPreview" src="{{ $profileAvatar }}" alt="Foto profil {{ $user?->nama ?? $user?->name ?? 'Pengguna' }}" class="profile-account-avatar profile-account-avatar-edit" onerror="this.onerror=null;this.src='{{ asset('img/profil.jpg') }}';">
                        <span class="profile-avatar-hint">Ubah</span>
                    </button>
                    <div class="profile-account-meta">
                        <div class="profile-account-name-wrap">
                            <h2>Edit Profil</h2>
                        </div>
                        <p class="profile-role">Perbarui data akun Anda. Klik foto untuk mengganti avatar.</p>
                        <div class="profile-account-contact">
                            <span>{{ $user?->email ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('profile.update') }}" class="profile-edit-form" enctype="multipart/form-data">
                @csrf
                @method('PATCH')
                <input id="profil" type="file" name="profil" class="profile-photo-input" accept="image/jpeg,image/png,image/webp">

                <div class="profile-edit-grid">
                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <input id="username" type="text" class="form-input" value="{{ $user?->username ?? '-' }}" readonly>
                        <small class="form-note">Username dipakai sebagai identitas login dan tidak bisa diubah dari halaman ini.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="nama">Nama Lengkap</label>
                        <input id="nama" type="text" name="nama" class="form-input" value="{{ old('nama', $user?->nama ?? $user?->name) }}" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <input id="email" type="email" name="email" class="form-input" value="{{ old('email', $user?->email) }}" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="nomor_telepon">Nomor Telepon</label>
                        <input
                            id="nomor_telepon"
                            type="text"
                            name="nomor_telepon"
                            class="form-input"
                            value="{{ old('nomor_telepon', $user?->nomor_telepon) }}"
                            placeholder="Contoh: 081234567890"
                        >
                    </div>

                    <div class="form-group form-group-with-action form-group-full">
                        <label class="form-label" for="status_akun">Status Akun</label>
                        <input
                            id="status_akun"
                            type="text"
                            class="form-input status-email-input-half"
                            value="Aktif"
                            readonly
                        >
                        <small class="form-note">Akun pengguna langsung aktif setelah registrasi.</small>
                    </div>
                </div>

                <div class="profile-edit-actions">
                    <a href="{{ route('user.profile') }}" class="btn-secondary">Batal</a>
                    <button type="submit" class="btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const photoInput = document.getElementById('profil');
            const photoPreview = document.getElementById('profilePhotoPreview');
            const photoTrigger = document.getElementById('profilePhotoTrigger');

            if (!photoInput || !photoPreview || !photoTrigger) return;

            photoTrigger.addEventListener('click', function () {
                photoInput.click();
            });

            photoInput.addEventListener('change', function () {
                const files = photoInput.files || [];
                const file = files[0];
                if (!file) return;
                if (!file.type.startsWith('image/')) return;

                photoPreview.src = URL.createObjectURL(file);
            });
        });
    </script>
@endsection
