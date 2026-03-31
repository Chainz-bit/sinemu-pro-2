export function initCarousel() {
    const carouselNavButtons = document.querySelectorAll('[data-carousel-target]');

    carouselNavButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const targetId = this.dataset.carouselTarget;
            const dir = this.dataset.carouselDir;
            const track = document.getElementById(targetId);
            if (!track) return;

            const shift = Math.max(260, Math.floor(track.clientWidth * 0.7));
            track.scrollBy({
                left: dir === 'next' ? shift : -shift,
                behavior: 'smooth'
            });
        });
    });
}
