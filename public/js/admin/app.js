/*
 * FILE: app.js
 * Tujuan:
 * - Titik masuk interaksi halaman admin.
 * - Menginisialisasi modul per fitur dan mengatur koordinasi antar modul.
 */

import { createRowMenu } from './modules/row-menu.js';
import { createProfileMenu } from './modules/profile-menu.js';
import { createNotificationModal } from './modules/notification-modal.js';
import { createSidebar } from './modules/sidebar.js';

document.addEventListener('DOMContentLoaded', function () {
    // Kumpulkan elemen UI yang dibutuhkan modul.
    const rowMenuTriggers = document.querySelectorAll('.row-menu-trigger');
    const profileWrap = document.querySelector('.profile-menu-wrap');
    const profileTrigger = document.querySelector('.profile-menu-trigger');
    const profileMenu = document.getElementById('profile-menu');
    const notificationTrigger = document.querySelector('.notification-trigger');
    const notificationModal = document.getElementById('notification-modal');
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebarBackdrop = document.querySelector('.sidebar-backdrop');
    const confirmBackdrop = document.getElementById('confirm-modal-backdrop');
    const confirmMessage = document.getElementById('confirm-modal-message');
    const confirmCancel = document.getElementById('confirm-modal-cancel');
    const confirmSubmit = document.getElementById('confirm-modal-submit');
    const deleteForms = document.querySelectorAll('form[data-confirm-delete]');
    let pendingDeleteForm = null;

    // Inisialisasi modul dengan dependency elemen yang relevan.
    const rowMenu = createRowMenu(rowMenuTriggers);
    const profile = createProfileMenu(profileWrap, profileTrigger, profileMenu);
    const notification = createNotificationModal(notificationTrigger, notificationModal);
    const sidebar = createSidebar(sidebarToggle, sidebarBackdrop);

    // Wiring antar modul: saat satu menu dibuka, menu lain ditutup.
    rowMenu.bind({
        closeProfile: profile.close,
        closeNotification: notification.close,
    });

    profile.bind({
        closeRowMenus: rowMenu.close,
        closeNotification: notification.close,
    });

    notification.bind({
        closeRowMenus: rowMenu.close,
        closeProfile: profile.close,
    });

    sidebar.bind();

    function openConfirmModal(form) {
        if (!confirmBackdrop || !confirmMessage) return;
        pendingDeleteForm = form;
        confirmMessage.textContent = form.getAttribute('data-confirm-message') || 'Yakin ingin menghapus data ini?';
        confirmBackdrop.hidden = false;
    }

    function closeConfirmModal() {
        if (!confirmBackdrop) return;
        confirmBackdrop.hidden = true;
        pendingDeleteForm = null;
    }

    deleteForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.confirmed === '1') {
                form.dataset.confirmed = '0';
                return;
            }

            event.preventDefault();
            openConfirmModal(form);
        });
    });

    confirmSubmit?.addEventListener('click', function () {
        if (!pendingDeleteForm) return;
        const form = pendingDeleteForm;
        form.dataset.confirmed = '1';
        closeConfirmModal();
        form.requestSubmit();
    });

    confirmCancel?.addEventListener('click', function () {
        closeConfirmModal();
    });

    confirmBackdrop?.addEventListener('click', function (event) {
        if (event.target === confirmBackdrop) {
            closeConfirmModal();
        }
    });

    // Klik area luar menutup semua popover/dropdown.
    document.addEventListener('click', function () {
        rowMenu.close();
        profile.close();
        notification.close();
    });

    // ESC sebagai shortcut menutup semua panel aktif.
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            rowMenu.close();
            profile.close();
            notification.close();
            sidebar.close();
            closeConfirmModal();
        }
    });
});
