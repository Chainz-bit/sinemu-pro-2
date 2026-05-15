@extends('super.layouts.app')

@php
    $pageTitle = 'Edit Profil - Super Admin - SiNemu';
    $activeMenu = 'profile';
    $searchAction = route('super.admins.index');
    $searchPlaceholder = 'Cari kecamatan atau instansi';
    $topbarBackUrl = route('super.profile');
    $topbarBackLabel = 'Kembali ke Profil';
    $hideSuperSidebar = true;
    $hideSuperSearch = true;
@endphp

@section('page-content')
    <div class="profile-page-content profile-page-content-edit super-page-content">
        <section class="profile-account-card">
            <div class="profile-account-top">
                <div class="profile-account-main">
                    <button type="button" id="profilePhotoTrigger" class="profile-avatar-trigger" aria-label="Ganti foto profil">
                        <img id="profilePhotoPreview" src="{{ $profileAvatar }}" alt="Foto profil {{ $superAdmin?->nama ?? 'Super Admin' }}" class="profile-account-avatar profile-account-avatar-edit" onerror="this.onerror=null;this.src='{{ asset('img/profil.jpg') }}';">
                        <span class="profile-avatar-hint">Ubah</span>
                    </button>
                    <div class="profile-account-meta">
                        <div class="profile-account-name-wrap">
                            <h2>Edit Profil Super Admin</h2>
                        </div>
                        <p class="profile-role">Perbarui data akun super admin. Username tetap dan tidak dapat diubah.</p>
                        <div class="profile-account-contact">
                            <span>{{ $superAdmin?->email ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('super.profile.update') }}" class="profile-edit-form" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <input id="profil" type="file" name="profil" class="profile-photo-input" accept="image/jpeg,image/png,image/webp">

                <div class="profile-edit-grid">
                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <input id="username" type="text" class="form-input" value="{{ $superAdmin?->username ?? '-' }}" readonly>
                        <small class="form-note">Username dipakai sebagai identitas login dan tidak dapat diubah dari halaman ini.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="nama">Nama Super Admin</label>
                        <input id="nama" type="text" name="nama" class="form-input" value="{{ old('nama', $superAdmin?->nama) }}" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <input id="email" type="email" name="email" class="form-input" value="{{ old('email', $superAdmin?->email) }}" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="current_password">Password Saat Ini</label>
                        <div class="super-password-field">
                            <input id="current_password" type="password" name="current_password" class="form-input" autocomplete="current-password" placeholder="Wajib diisi jika ubah password">
                            <button type="button" class="super-password-toggle" data-password-toggle="current_password" aria-label="Tampilkan password" aria-pressed="false">
                                <iconify-icon icon="mdi:eye-outline"></iconify-icon>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Password Baru</label>
                        <div class="super-password-field">
                            <input id="password" type="password" name="password" class="form-input" autocomplete="new-password" placeholder="Kosongkan jika tidak diubah">
                            <button type="button" class="super-password-toggle" data-password-toggle="password" aria-label="Tampilkan password" aria-pressed="false">
                                <iconify-icon icon="mdi:eye-outline"></iconify-icon>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password_confirmation">Konfirmasi Password Baru</label>
                        <div class="super-password-field">
                            <input id="password_confirmation" type="password" name="password_confirmation" class="form-input" autocomplete="new-password" placeholder="Ulangi password baru">
                            <button type="button" class="super-password-toggle" data-password-toggle="password_confirmation" aria-label="Tampilkan password" aria-pressed="false">
                                <iconify-icon icon="mdi:eye-outline"></iconify-icon>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="profile-edit-actions">
                    <a href="{{ route('super.profile') }}" class="btn-secondary">Batal</a>
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
                const [file] = photoInput.files || [];
                if (!file || !file.type.startsWith('image/')) return;

                photoPreview.src = URL.createObjectURL(file);
            });
        });
    </script>
@endsection
