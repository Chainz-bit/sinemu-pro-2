import { initNavbar } from './home/navbar.js';
import { initFilterAndCounts } from './home/filter.js';
import { initActions } from './home/actions.js';
import { initCarousel } from './home/carousel.js';
import { initMap } from './home/map.js';

document.addEventListener('DOMContentLoaded', function () {
    initNavbar();
    initFilterAndCounts();
    initActions();
    initCarousel();
    initMap();
});
