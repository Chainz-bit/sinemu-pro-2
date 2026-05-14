/*
 * FILE: super/app.js
 * Tujuan:
 * - Titik masuk interaksi halaman super admin.
 * - Saat ini memakai shell dashboard pengelola barang yang sama untuk sidebar, topbar,
 *   row menu, notifikasi, dan modal konfirmasi.
 */

import '../manager/app.js';

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        const targetId = button.dataset.passwordToggle;
        const input = targetId ? document.getElementById(targetId) : null;
        const icon = button.querySelector('iconify-icon');
        if (!input) return;

        button.addEventListener('click', function () {
            const shouldShow = input.type === 'password';
            input.type = shouldShow ? 'text' : 'password';
            button.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
            button.setAttribute('aria-label', shouldShow ? 'Sembunyikan password' : 'Tampilkan password');

            if (icon) {
                icon.setAttribute('icon', shouldShow ? 'mdi:eye-off-outline' : 'mdi:eye-outline');
            }
        });
    });

    const form = document.querySelector('[data-super-manager-form]');
    if (!form) return;

    const firstInvalidField = form.querySelector('.is-invalid');
    if (firstInvalidField) {
        firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstInvalidField.focus({ preventScroll: true });
    }

    form.addEventListener('submit', function () {
        const submitButton = form.querySelector('button[type="submit"]');
        if (!submitButton) return;

        submitButton.disabled = true;
        submitButton.textContent = submitButton.dataset.loadingLabel || 'Menyimpan...';
        form.classList.add('is-submitting');
    });

    const cancelLink = form.querySelector('[data-cancel-form-link]');
    if (!cancelLink) return;

    const initialData = new FormData(form);
    const initialSignature = JSON.stringify(Array.from(initialData.entries()));

    function isDirty() {
        return JSON.stringify(Array.from(new FormData(form).entries())) !== initialSignature;
    }

    cancelLink.addEventListener('click', function (event) {
        if (!isDirty()) return;

        event.preventDefault();
        const targetUrl = cancelLink.href;
        const backdrop = document.getElementById('confirm-modal-backdrop');
        const title = document.getElementById('confirm-modal-title');
        const message = document.getElementById('confirm-modal-message');
        const cancel = document.getElementById('confirm-modal-cancel');
        const submit = document.getElementById('confirm-modal-submit');

        if (!backdrop || !submit || !cancel || !message) {
            window.location.href = targetUrl;
            return;
        }

        if (title) {
            title.textContent = 'Batalkan Pengisian Form?';
        }

        message.textContent = 'Data yang sudah diisi belum disimpan dan akan hilang jika Anda keluar dari halaman ini.';
        cancel.textContent = 'Tetap di Halaman';
        submit.textContent = 'Ya, Batalkan';
        submit.classList.remove('confirm-btn-danger');
        submit.classList.add('confirm-btn-primary');
        backdrop.hidden = false;

        const handleSubmit = function () {
            window.location.href = targetUrl;
        };

        const handleClose = function () {
            cancel.textContent = 'Batal';
            submit.textContent = 'Hapus';
            submit.classList.remove('confirm-btn-primary');
            submit.classList.add('confirm-btn-danger');
            submit.removeEventListener('click', handleSubmit);
            cancel.removeEventListener('click', handleClose);
            backdrop.removeEventListener('click', handleBackdrop);
        };

        const handleBackdrop = function (backdropEvent) {
            if (backdropEvent.target === backdrop) {
                handleClose();
            }
        };

        submit.addEventListener('click', handleSubmit, { once: true });
        cancel.addEventListener('click', handleClose, { once: true });
        backdrop.addEventListener('click', handleBackdrop);
    });
});
