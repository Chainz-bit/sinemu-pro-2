async function bootNavbar() {
    if (!document.getElementById('mainNavBar')) return;
    const mod = await import('./navbar.js');
    mod.initNavbar();
}

async function bootFilterAndCounts() {
    if (!document.getElementById('filterForm')) return;
    const mod = await import('./filter.js');
    mod.initFilterAndCounts();
}

async function bootCarousel() {
    if (!document.querySelector('[data-carousel-target]')) return;
    const mod = await import('./carousel.js');
    mod.initCarousel();
}

async function bootContactForm() {
    if (!document.getElementById('contactForm')) return;
    const mod = await import('./contact.js');
    mod.initContactForm();
}

async function bootMap() {
    if (!document.getElementById('pickupMap')) return;
    const mod = await import('./map.js');
    mod.initMap();
}

async function bootChatbot() {
    if (!document.getElementById('chatbotToggle')) return;
    const mod = await import('./chatbot.js');
    mod.initChatbot();
}

document.addEventListener('DOMContentLoaded', function () {
    void bootNavbar();
    void bootFilterAndCounts();
    void bootCarousel();
    void bootContactForm();
    void bootMap();
    void bootChatbot();
});
