export function initNavbar() {
    const navBar = document.getElementById('mainNavBar');
    if (!navBar) return;

    function getScrollOffset() {
        return Math.ceil(navBar.getBoundingClientRect().height) + 16;
    }

    function resolveHashTarget(hash) {
        const normalizedHash = hash === '#lokasi-pengambilan' ? '#lokasi' : hash;
        if (!normalizedHash || normalizedHash === '#') {
            return null;
        }

        try {
            return document.getElementById(decodeURIComponent(normalizedHash.slice(1)));
        } catch (error) {
            return null;
        }
    }

    function scrollToElement(target, behavior) {
        const targetTop = target.getBoundingClientRect().top + window.scrollY - getScrollOffset();
        window.scrollTo({
            top: Math.max(0, targetTop),
            behavior: behavior || 'auto'
        });
    }

    function clearHashAndScrollTop(behavior) {
        if (typeof window.history.replaceState === 'function') {
            window.history.replaceState(null, '', window.location.pathname + window.location.search);
        }

        window.scrollTo({
            top: 0,
            behavior: behavior || 'auto'
        });
    }

    function normalizeInitialScroll() {
        const target = resolveHashTarget(window.location.hash);

        if (target) {
            window.requestAnimationFrame(function () {
                scrollToElement(target, 'auto');
            });
            return;
        }

        clearHashAndScrollTop('auto');
    }

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

    document.querySelectorAll('a[href^="#"]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            const target = resolveHashTarget(link.getAttribute('href') || '');
            if (!target) {
                return;
            }

            event.preventDefault();
            const hash = '#' + target.id;
            if (typeof window.history.pushState === 'function' && window.location.hash !== hash) {
                window.history.pushState(null, '', hash);
            }

            scrollToElement(target, 'smooth');
        });
    });

    document.querySelectorAll('[data-home-link]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            const linkUrl = new URL(link.href, window.location.href);
            const isCurrentPage = linkUrl.origin === window.location.origin
                && linkUrl.pathname === window.location.pathname;

            if (!isCurrentPage) {
                return;
            }

            event.preventDefault();
            clearHashAndScrollTop('smooth');
        });
    });

    window.addEventListener('pageshow', function () {
        if (!window.location.hash) {
            window.requestAnimationFrame(function () {
                window.scrollTo(0, 0);
            });
        }
    });

    normalizeInitialScroll();
}
