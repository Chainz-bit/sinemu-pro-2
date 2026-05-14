import 'bootstrap/dist/js/bootstrap.bundle.min.js';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-app-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(button.getAttribute('data-app-password-toggle'));
            if (!input) return;

            const shouldShow = input.type === 'password';
            input.type = shouldShow ? 'text' : 'password';
            button.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
            button.setAttribute('aria-label', shouldShow ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        });
    });
});
