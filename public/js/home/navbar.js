export function initNavbar() {
    const navBar = document.getElementById('mainNavBar');
    if (!navBar) return;

    let lastScrollY = window.scrollY;
    let ticking = false;

    function handleNav() {
        const current = window.scrollY;

        if (current > lastScrollY + 5 && current > 120) {
            navBar.classList.add('nav-hidden');
        } else if (current < lastScrollY - 5) {
            navBar.classList.remove('nav-hidden');
        }

        lastScrollY = current;
        ticking = false;
    }

    window.addEventListener(
        'scroll',
        function () {
            if (!ticking) {
                window.requestAnimationFrame(handleNav);
                ticking = true;
            }
        },
        { passive: true }
    );
}
