@extends('super.layouts.app')

@php
    $pageTitle = 'Pengaturan Super Admin - SiNemu';
    $activeMenu = 'settings';
    $hideSuperSidebar = true;
    $hideSuperSearch = true;
    $topbarBackUrl = route('super.dashboard');
    $topbarBackLabel = 'Kembali ke Dashboard';
@endphp

@section('page-content')
    <section class="settings-page">
        <header class="settings-header">
            <h1>Pengaturan Super Admin</h1>
            <p>Kelola identitas akun utama dan preferensi akses untuk operasional SiNemu.</p>
        </header>

        <form method="POST" action="{{ route('super.settings.update') }}" class="settings-form">
            @csrf
            @method('PUT')

            <article class="settings-card">
                <header class="settings-card-head">
                    <h2>
                        <iconify-icon icon="mdi:shield-crown-outline"></iconify-icon>
                        Identitas Akun
                    </h2>
                </header>

                <div class="settings-grid">
                    <div class="settings-field">
                        <label for="nama">Nama Super Admin</label>
                        <input id="nama" name="nama" type="text" class="form-input" maxlength="255" required value="{{ old('nama', $superAdmin?->nama) }}">
                        <small class="settings-field-help">Nama penanggung jawab utama yang tampil pada area super admin.</small>
                    </div>

                    <div class="settings-field">
                        <label for="username">Username</label>
                        <input id="username" name="username" type="text" class="form-input" maxlength="255" required value="{{ old('username', $superAdmin?->username) }}">
                        <small class="settings-field-help">Username yang dipakai untuk masuk ke portal super admin.</small>
                    </div>

                    <div class="settings-field settings-field-full">
                        <label for="email">Email Utama</label>
                        <input id="email" name="email" type="email" class="form-input" maxlength="255" required value="{{ old('email', $superAdmin?->email) }}">
                        <small class="settings-field-help">Email utama untuk notifikasi sistem dan pemulihan akses akun.</small>
                        <small class="settings-field-error" id="email_inline_error" role="status" aria-live="polite"></small>
                    </div>
                </div>
            </article>

            <article class="settings-card">
                <header class="settings-card-head">
                    <h2>
                        <iconify-icon icon="mdi:history"></iconify-icon>
                        Log / Riwayat
                    </h2>
                </header>

                <div class="settings-log-row">
                    <div class="settings-log-text">
                        <strong>Riwayat Verifikasi Super Admin</strong>
                        <p>Lihat daftar aktivitas verifikasi admin, status terbaru, dan jejak perubahan yang tercatat.</p>
                    </div>
                    <a href="{{ route('super.settings.history') }}">Buka Riwayat</a>
                </div>
            </article>

            <div class="settings-actions">
                <span class="settings-dirty-indicator" id="settingsDirtyIndicator" aria-live="polite">Perubahan belum disimpan</span>
                <a href="{{ route('super.dashboard') }}" class="settings-btn settings-btn-light">Batalkan Perubahan</a>
                <button type="submit" class="settings-btn settings-btn-primary" id="settingsSaveButton">Simpan Perubahan</button>
            </div>
        </form>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('.settings-form');
            const saveButton = document.getElementById('settingsSaveButton');
            const dirtyIndicator = document.getElementById('settingsDirtyIndicator');
            const emailInput = document.getElementById('email');
            const emailInlineError = document.getElementById('email_inline_error');
            if (!form || !saveButton) return;

            const fields = Array.from(form.querySelectorAll('input, textarea, select'))
                .filter(function (element) {
                    return element.name && !element.disabled;
                });

            const initialValues = new Map();
            fields.forEach(function (field) {
                initialValues.set(field.name, field.value);
            });

            function hasChanges() {
                return fields.some(function (field) {
                    return field.value !== initialValues.get(field.name);
                });
            }

            function validateEmailInline() {
                if (!emailInput) return true;

                const value = emailInput.value.trim();
                const valid = value.length > 0 && emailInput.checkValidity();

                if (emailInlineError) {
                    emailInlineError.textContent = valid || value.length === 0
                        ? ''
                        : 'Format email tidak valid. Contoh: superadmin@sinemu.id';
                }

                emailInput.classList.toggle('is-invalid', !valid && value.length > 0);
                return valid;
            }

            function syncSaveState() {
                const changed = hasChanges();
                const emailValid = validateEmailInline();

                saveButton.disabled = !(changed && emailValid);
                if (dirtyIndicator) {
                    dirtyIndicator.classList.toggle('is-visible', changed);
                }
            }

            fields.forEach(function (field) {
                field.addEventListener('input', syncSaveState);
                field.addEventListener('change', syncSaveState);
            });

            form.addEventListener('submit', function (event) {
                if (!validateEmailInline()) {
                    event.preventDefault();
                    emailInput?.focus();
                }
            });

            syncSaveState();
        });
    </script>
@endsection
