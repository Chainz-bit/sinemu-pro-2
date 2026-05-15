@extends('manager::layouts.app')

@php
    $managerRoleLabel = \App\Support\RoleLabels::manager();
    $managerRoleLabelLower = \App\Support\RoleLabels::managerLower();
    $pageTitle = 'Edit Profil - ' . $managerRoleLabel . ' - SiNemu';
    $activeMenu = 'profile';
    $hideSearch = true;
    $hideSidebar = true;
    $topbarBackUrl = manager_route('profile');
    $topbarBackLabel = 'Kembali ke Profil';
@endphp

@section('page-content')
    <div class="profile-page-content profile-page-content-edit">
        <section class="profile-account-card">
            <div class="profile-account-top">
                <div class="profile-account-main">
                    <button type="button" id="profilePhotoTrigger" class="profile-avatar-trigger" aria-label="Ganti foto profil">
                        <img id="profilePhotoPreview" src="{{ $profileAvatar }}" alt="Foto profil {{ $manager?->nama ?? $managerRoleLabel }}" class="profile-account-avatar profile-account-avatar-edit" onerror="this.onerror=null;this.src='{{ asset('img/profil.jpg') }}';">
                        <span class="profile-avatar-hint">Ubah</span>
                    </button>
                    <div class="profile-account-meta">
                        <div class="profile-account-name-wrap">
                            <h2>Edit Profil {{ $managerRoleLabel }}</h2>
                        </div>
                        <p class="profile-role">Perbarui data operasional akun {{ $managerRoleLabelLower }} Anda. Klik foto untuk mengganti avatar.</p>
                        <div class="profile-account-contact">
                            <span>{{ $manager?->email ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>
<form method="POST" action="{{ manager_route('profile.update') }}" class="profile-edit-form" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <input id="profil" type="file" name="profil" class="profile-photo-input" accept="image/jpeg,image/png,image/webp">

                <div class="profile-edit-grid">
                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <input id="username" type="text" class="form-input" value="{{ $manager?->username ?? '-' }}" readonly>
                        <small class="form-note">Username dipakai sebagai identitas login dan tidak bisa diubah dari halaman ini.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="nama">Nama {{ $managerRoleLabel }}</label>
                        <input id="nama" type="text" name="nama" class="form-input" value="{{ old('nama', $manager?->nama) }}" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <input id="email" type="email" name="email" class="form-input" value="{{ old('email', $manager?->email) }}" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="instansi">Instansi</label>
                        <input id="instansi" type="text" name="instansi" class="form-input" value="{{ old('instansi', $manager?->instansi) }}" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="kecamatan">Kecamatan</label>
                        <input
                            id="kecamatan"
                            type="text"
                            name="kecamatan"
                            class="form-input"
                            list="kecamatan-options"
                            value="{{ old('kecamatan', $manager?->kecamatan) }}"
                            placeholder="Pilih kecamatan"
                            autocomplete="off"
                            required
                        >
                        <datalist id="kecamatan-options">
                            @foreach($kecamatanOptions as $kecamatan)
                                <option value="{{ $kecamatan }}"></option>
                            @endforeach
                        </datalist>
                    </div>

                    <div class="form-group form-group-full">
                        <label class="form-label" for="alamat_lengkap">Alamat Operasional</label>
                        <textarea id="alamat_lengkap" name="alamat_lengkap" class="form-input form-textarea">{{ old('alamat_lengkap', $manager?->alamat_lengkap) }}</textarea>
                    </div>
                </div>

                <div class="profile-edit-actions">
                    <a href="{{ manager_route('profile') }}" class="btn-secondary">Batal</a>
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
                if (!file) return;

                if (!file.type.startsWith('image/')) return;
                photoPreview.src = URL.createObjectURL(file);
            });
        });
    </script>
@endsection
