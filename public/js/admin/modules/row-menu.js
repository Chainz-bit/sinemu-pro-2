/*
 * FILE: modules/row-menu.js
 * Tujuan:
 * - Mengelola dropdown aksi per baris tabel (ikon titik tiga).
 */

export function createRowMenu(triggers) {
    function placeFloatingMenu(menu, button) {
        menu.classList.remove('open-up', 'open-down');

        // Ukur dimensi menu tanpa menampilkannya ke pengguna.
        menu.style.visibility = 'hidden';
        menu.classList.add('open');
        const menuRect = menu.getBoundingClientRect();
        menu.classList.remove('open');
        menu.style.visibility = '';

        const triggerRect = button.getBoundingClientRect();
        const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
        const spaceAbove = triggerRect.top;
        const spaceBelow = viewportHeight - triggerRect.bottom;
        const gap = 6;
        const menuWidth = menuRect.width || 160;
        const menuHeight = menuRect.height || 120;

        // Kunci ke viewport agar tidak ter-clipping parent overflow.
        menu.style.position = 'fixed';
        menu.style.right = 'auto';
        menu.style.bottom = 'auto';
        menu.style.zIndex = '9999';

        const openUp = spaceBelow < (menuHeight + 12) && spaceAbove > spaceBelow;
        if (openUp) {
            menu.classList.add('open-up');
            menu.style.top = `${Math.max(8, triggerRect.top - menuHeight - gap)}px`;
        } else {
            menu.classList.add('open-down');
            menu.style.top = `${Math.min(viewportHeight - menuHeight - 8, triggerRect.bottom + gap)}px`;
        }

        const preferredLeft = triggerRect.right - menuWidth;
        const clampedLeft = Math.min(Math.max(8, preferredLeft), viewportWidth - menuWidth - 8);
        menu.style.left = `${clampedLeft}px`;
    }

    function resetFloatingStyle(menu) {
        menu.style.position = '';
        menu.style.left = '';
        menu.style.top = '';
        menu.style.right = '';
        menu.style.bottom = '';
        menu.style.zIndex = '';
    }

    // Menutup semua menu, kecuali id tertentu (jika diberikan).
    function close(exceptId) {
        document.querySelectorAll('.row-menu').forEach(function (menu) {
            if (!exceptId || menu.id !== exceptId) {
                menu.classList.remove('open');
                menu.classList.remove('open-up', 'open-down');
                resetFloatingStyle(menu);
            }
        });
    }

    // Pasang event click ke semua trigger menu baris.
    function bind(options) {
        triggers.forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.stopPropagation();
                const targetId = button.getAttribute('data-menu-target');
                const menu = targetId ? document.getElementById(targetId) : null;
                const willOpen = menu && !menu.classList.contains('open');

                close(targetId);
                options?.closeProfile?.();
                options?.closeNotification?.();

                if (menu && willOpen) {
                    placeFloatingMenu(menu, button);
                    menu.classList.add('open');
                }
            });
        });
    }

    return { close, bind };
}
