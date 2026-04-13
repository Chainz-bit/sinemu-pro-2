export function initCarousel() {
    const carouselNavButtons = document.querySelectorAll('[data-carousel-target]');
    const draggableTracks = document.querySelectorAll('.carousel-draggable');

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

    draggableTracks.forEach(function (track) {
        let isDown = false;
        let startX = 0;
        let scrollLeft = 0;

        track.addEventListener('pointerdown', function (event) {
            const interactiveTarget = event.target.closest('a, button, input, textarea, select, label');
            if (interactiveTarget) return;
            if (event.button !== 0 && event.pointerType === 'mouse') return;
            isDown = true;
            track.classList.add('dragging');
            track.setPointerCapture?.(event.pointerId);
            startX = event.clientX;
            scrollLeft = track.scrollLeft;
            event.preventDefault();
        });

        track.addEventListener('pointermove', function (event) {
            if (!isDown) return;
            const walk = (event.clientX - startX) * 1.2;
            track.scrollLeft = scrollLeft - walk;
        });

        function endDrag() {
            isDown = false;
            track.classList.remove('dragging');
        }

        track.addEventListener('pointerup', endDrag);
        track.addEventListener('pointerleave', endDrag);
        track.addEventListener('pointercancel', endDrag);
    });
}
