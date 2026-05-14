const DEFAULT_PHONE = '0851-7438-6642';
const DEFAULT_HOURS = '08.00-20.00 WIB';
const GEOCODE_CACHE_KEY = 'sinemu_pickup_geocode_cache_v1';
const DEFAULT_MANAGER_LABEL = String(window.__SINEMU_ROLE_LABELS?.managerDisplayName || window.__SINEMU_ROLE_LABELS?.adminDisplayName || 'Pengelola Barang');

function parsePickupLocations() {
    const node = document.getElementById('pickupLocationsData');
    if (!node) {
        return [];
    }

    try {
        const parsed = JSON.parse(node.textContent || '[]');
        if (!Array.isArray(parsed)) {
            return [];
        }

        return parsed.map(function (item, index) {
            return {
                id: Number(item.id || (index + 1)),
                name: String(item.name || 'Lokasi Pengambilan SiNemu'),
                managerLabel: String(item.manager_label || DEFAULT_MANAGER_LABEL),
                address: String(item.address || ''),
                kecamatan: String(item.kecamatan || ''),
                phone: String(item.phone || DEFAULT_PHONE),
                hours: String(item.hours || DEFAULT_HOURS),
                lat: typeof item.lat === 'number' ? item.lat : null,
                lng: typeof item.lng === 'number' ? item.lng : null,
            };
        });
    } catch (error) {
        return [];
    }
}

function buildMapsLink(location) {
    const query = encodeURIComponent([location.name, location.address, location.kecamatan, 'Indramayu'].filter(Boolean).join(', '));
    return 'https://www.google.com/maps/search/?api=1&query=' + query;
}

function buildRouteLink(location, userCoords) {
    if (!(Number.isFinite(location.lat) && Number.isFinite(location.lng))) {
        return buildMapsLink(location);
    }

    const destination = encodeURIComponent(location.lat + ',' + location.lng);

    if (!userCoords) {
        return 'https://www.google.com/maps/dir/?api=1&destination=' + destination;
    }

    const origin = encodeURIComponent(userCoords.lat + ',' + userCoords.lng);
    return 'https://www.google.com/maps/dir/?api=1&origin=' + origin + '&destination=' + destination;
}

function toRadians(value) {
    return (value * Math.PI) / 180;
}

function calculateDistanceKm(a, b) {
    const earthRadiusKm = 6371;
    const dLat = toRadians(b.lat - a.lat);
    const dLng = toRadians(b.lng - a.lng);

    const sinLat = Math.sin(dLat / 2);
    const sinLng = Math.sin(dLng / 2);

    const h = sinLat * sinLat
        + Math.cos(toRadians(a.lat)) * Math.cos(toRadians(b.lat)) * sinLng * sinLng;

    const c = 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));

    return earthRadiusKm * c;
}

function formatDistance(km) {
    if (!Number.isFinite(km)) {
        return 'Jarak tidak tersedia';
    }

    if (km < 1) {
        return Math.round(km * 1000) + ' m dari lokasi Anda';
    }

    return km.toFixed(1) + ' km dari lokasi Anda';
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function loadGeocodeCache() {
    try {
        return JSON.parse(localStorage.getItem(GEOCODE_CACHE_KEY) || '{}');
    } catch (error) {
        return {};
    }
}

function saveGeocodeCache(cache) {
    try {
        localStorage.setItem(GEOCODE_CACHE_KEY, JSON.stringify(cache));
    } catch (error) {
        // ignore
    }
}

function cacheKeyForLocation(location) {
    return [location.address, location.kecamatan, 'Indramayu'].filter(Boolean).join(' | ').toLowerCase();
}

async function geocodeLocation(location) {
    const cache = loadGeocodeCache();
    const key = cacheKeyForLocation(location);

    if (cache[key]) {
        return cache[key];
    }

    const query = encodeURIComponent([location.address, location.kecamatan, 'Indramayu', 'Jawa Barat', 'Indonesia'].filter(Boolean).join(', '));
    const url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=' + query;

    try {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json'
            }
        });

        if (!response.ok) {
            return null;
        }

        const result = await response.json();
        if (!Array.isArray(result) || result.length === 0) {
            return null;
        }

        const coords = {
            lat: Number(result[0].lat),
            lng: Number(result[0].lon)
        };

        if (!Number.isFinite(coords.lat) || !Number.isFinite(coords.lng)) {
            return null;
        }

        cache[key] = coords;
        saveGeocodeCache(cache);

        return coords;
    } catch (error) {
        return null;
    }
}

export function initMap() {
    const locations = parsePickupLocations();

    const mapElement = document.getElementById('pickupMap');
    const listElement = document.getElementById('pickupLocationList');
    const selectedName = document.getElementById('selectedLocationName');
    const selectedManager = document.getElementById('selectedLocationManager');
    const selectedAddress = document.getElementById('selectedLocationAddress');
    const selectedHours = document.getElementById('selectedLocationHours');
    const selectedDistance = document.getElementById('selectedLocationDistance');
    const selectedOpenMaps = document.getElementById('selectedOpenMaps');
    const selectedGetRoute = document.getElementById('selectedGetRoute');
    const locateMeButton = document.getElementById('locateMeButton');
    const carouselPrevButton = document.getElementById('pickupCarouselPrev');
    const carouselNextButton = document.getElementById('pickupCarouselNext');

    if (!mapElement || !listElement || typeof window.L === 'undefined') {
        return;
    }

    const map = window.L.map(mapElement, {
        zoomControl: true,
        dragging: false,
        scrollWheelZoom: false,
        touchZoom: false,
        doubleClickZoom: false,
        boxZoom: false,
        keyboard: false,
        tap: false
    }).setView([-6.3265, 108.3205], 12);

    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors | Data lokasi: SiNemu Indramayu'
    }).addTo(map);

    if (locations.length === 0) {
        if (selectedName) selectedName.textContent = 'Belum ada lokasi aktif';
        if (selectedAddress) selectedAddress.textContent = 'Lokasi akan muncul setelah ' + DEFAULT_MANAGER_LABEL.toLowerCase() + ' diverifikasi super admin.';
        if (selectedHours) selectedHours.textContent = 'Jam Operasional: -';
        if (selectedDistance) selectedDistance.textContent = 'Jarak: -';
        if (selectedOpenMaps) {
            selectedOpenMaps.removeAttribute('href');
            selectedOpenMaps.setAttribute('aria-disabled', 'true');
        }
        if (selectedGetRoute) selectedGetRoute.setAttribute('disabled', 'disabled');
        if (locateMeButton) locateMeButton.setAttribute('disabled', 'disabled');
        if (carouselPrevButton || carouselNextButton) {
            [carouselPrevButton, carouselNextButton].forEach(function (button) {
                if (!button) return;
                button.hidden = true;
                button.setAttribute('aria-hidden', 'true');
                button.tabIndex = -1;
            });
        }

        listElement.innerHTML = [
            '<article class="lokasi-empty-state" aria-label="Belum ada titik pengambilan aktif">',
            '<div class="lokasi-empty-content">',
            '<div class="lokasi-empty-illustration" aria-hidden="true">',
            '<span class="lokasi-empty-map"></span>',
            '<span class="lokasi-empty-pin"></span>',
            '</div>',
            '<p class="lokasi-empty-text">Belum ada titik pengambilan aktif</p>',
            '</div>',
            '</article>'
        ].join('');

        setTimeout(function () {
            map.invalidateSize();
        }, 180);

        return;
    }

    let selectedLocationId = locations[0].id;
    let userCoords = null;
    let userMarker = null;
    let userRadius = null;
    const markerById = new Map();
    const locationById = new Map();

    function setCarouselControlsVisible(isVisible) {
        [carouselPrevButton, carouselNextButton].forEach(function (button) {
            if (!button) {
                return;
            }

            button.hidden = !isVisible;
            button.setAttribute('aria-hidden', isVisible ? 'false' : 'true');
            button.tabIndex = isVisible ? 0 : -1;
        });
    }

    function buildPopupHtml(location) {
        const routeUrl = Number.isFinite(location.lat) && Number.isFinite(location.lng)
            ? buildRouteLink(location, userCoords)
            : buildMapsLink(location);

        return [
            '<div class="pickup-popup">',
            '<strong>' + escapeHtml(location.name) + '</strong><br>',
            '<span>' + escapeHtml(location.address) + '</span><br>',
            '<span>Kecamatan: ' + escapeHtml(location.kecamatan || '-') + '</span><br>',
            '<span>Jam: ' + escapeHtml(location.hours) + '</span><br>',
            '<a class="pickup-popup-link" target="_blank" rel="noopener" href="' + routeUrl + '">Petunjuk Arah</a>',
            '</div>'
        ].join('');
    }

    function setLocateButtonState(isActive, isLoading) {
        if (!locateMeButton) {
            return;
        }

        if (isLoading) {
            locateMeButton.disabled = true;
            locateMeButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Mendeteksi lokasi...';
            return;
        }

        locateMeButton.disabled = false;
        locateMeButton.innerHTML = isActive
            ? '<i class="fa-solid fa-circle-check me-2"></i>Lokasi Saya Aktif'
            : '<i class="fa-solid fa-location-crosshairs me-2"></i>Lokasi Saya';
    }

    function getSelectedLocation() {
        return locations.find(function (location) {
            return location.id === selectedLocationId;
        }) || locations[0];
    }

    function getDistanceText(location) {
        if (!userCoords) {
            return 'Aktifkan Lokasi Saya untuk estimasi jarak.';
        }

        const distanceKm = calculateDistanceKm(userCoords, location);
        return formatDistance(distanceKm);
    }

    function getDistanceBadgeText(location) {
        if (!userCoords) {
            return 'Estimasi jarak';
        }

        return getDistanceText(location);
    }

    function focusOnLocation(location, zoom) {
        map.setView([location.lat, location.lng], zoom || 14, {
            animate: true,
            duration: 0.4
        });

        const marker = markerById.get(location.id);
        if (marker) {
            marker.openPopup();
        }
    }

    function updateSelectedInfo() {
        const location = getSelectedLocation();

        if (selectedName) selectedName.textContent = location.name;
        if (selectedManager) selectedManager.textContent = location.managerLabel || DEFAULT_MANAGER_LABEL;
        if (selectedAddress) selectedAddress.textContent = [location.address, location.kecamatan].filter(Boolean).join(', ');
        if (selectedHours) selectedHours.textContent = 'Jam Operasional: ' + location.hours;
        if (selectedDistance) selectedDistance.textContent = 'Jarak: ' + getDistanceText(location);

        if (selectedOpenMaps) {
            selectedOpenMaps.href = buildMapsLink(location);
            selectedOpenMaps.removeAttribute('aria-disabled');
        }

        if (selectedGetRoute) {
            selectedGetRoute.dataset.locationId = String(location.id);
            selectedGetRoute.removeAttribute('disabled');
        }
    }

    function renderLocationList() {
        listElement.classList.toggle('is-single', locations.length === 1);
        setCarouselControlsVisible(locations.length > 1);

        listElement.innerHTML = locations.map(function (location) {
            const isActive = location.id === selectedLocationId;
            const mapLink = buildMapsLink(location);
            const routeLink = Number.isFinite(location.lat) && Number.isFinite(location.lng)
                ? buildRouteLink(location, userCoords)
                : mapLink;
            const addressLabel = [location.address, location.kecamatan].filter(Boolean).join(', ');

            return [
                '<article class="lokasi-item' + (isActive ? ' is-active' : '') + '" data-location-id="' + location.id + '">',
                '<div class="lokasi-item-top">',
                '<div class="lokasi-item-heading">',
                '<small class="lokasi-item-manager">' + escapeHtml(location.managerLabel || DEFAULT_MANAGER_LABEL) + '</small>',
                '<h4>' + escapeHtml(location.name) + '</h4>',
                '</div>',
                '<span class="lokasi-item-distance" title="' + escapeHtml(getDistanceText(location)) + '"><i class="fa-solid fa-location-arrow"></i>' + escapeHtml(getDistanceBadgeText(location)) + '</span>',
                '</div>',
                '<p class="lokasi-item-address"><i class="fa-solid fa-location-dot"></i><span>' + escapeHtml(addressLabel) + '</span></p>',
                '<div class="lokasi-item-meta">',
                '<span><i class="fa-regular fa-clock"></i><strong>' + escapeHtml(location.hours) + '</strong></span>',
                '<span><i class="fa-solid fa-phone"></i><strong>' + escapeHtml(location.phone) + '</strong></span>',
                '</div>',
                '<div class="lokasi-item-actions">',
                '<a href="' + mapLink + '" target="_blank" rel="noopener" class="lokasi-mini-btn lokasi-mini-btn-primary" data-action="open-maps"><i class="fa-regular fa-map"></i>Buka di Maps</a>',
                '<a href="' + routeLink + '" target="_blank" rel="noopener" class="lokasi-mini-btn lokasi-mini-btn-secondary" data-action="get-route"><i class="fa-solid fa-route"></i>Dapatkan Route</a>',
                '</div>',
                '</article>'
            ].join('');
        }).join('');

        window.requestAnimationFrame(function () {
            setCarouselControlsVisible(locations.length > 1 && listElement.scrollWidth > listElement.clientWidth + 8);
        });
    }

    function refreshMarkerPopups() {
        markerById.forEach(function (marker, locationId) {
            const location = locationById.get(locationId);
            if (!location) {
                return;
            }

            marker.setPopupContent(buildPopupHtml(location));
        });
    }

    function getCarouselStep() {
        const firstCard = listElement.querySelector('.lokasi-item');
        const cardWidth = firstCard ? firstCard.getBoundingClientRect().width : 320;
        return Math.max(240, Math.round(cardWidth + 12));
    }

    function scrollCarousel(direction) {
        listElement.scrollBy({
            left: direction * getCarouselStep(),
            behavior: 'smooth'
        });
    }

    function scrollSelectedCardIntoView() {
        const activeCard = listElement.querySelector('.lokasi-item.is-active');
        if (!activeCard) {
            return;
        }

        activeCard.scrollIntoView({
            behavior: 'smooth',
            inline: 'nearest',
            block: 'nearest'
        });
    }

    function requestUserLocation() {
        if (!navigator.geolocation) {
            if (selectedDistance) selectedDistance.textContent = 'Jarak: Browser Anda tidak mendukung geolokasi.';
            return;
        }

        setLocateButtonState(false, true);

        navigator.geolocation.getCurrentPosition(
            function (position) {
                userCoords = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };

                if (userMarker) {
                    map.removeLayer(userMarker);
                }

                if (userRadius) {
                    map.removeLayer(userRadius);
                }

                userMarker = window.L.marker([userCoords.lat, userCoords.lng]).addTo(map);
                userMarker.bindPopup('Lokasi Anda saat ini').openPopup();

                userRadius = window.L.circle([userCoords.lat, userCoords.lng], {
                    radius: 180,
                    color: '#0d9488',
                    fillColor: '#14b8a6',
                    fillOpacity: 0.18
                }).addTo(map);

                renderLocationList();
                refreshMarkerPopups();
                updateSelectedInfo();
                map.setView([userCoords.lat, userCoords.lng], 13, { animate: true, duration: 0.35 });

                setLocateButtonState(true, false);
            },
            function () {
                if (selectedDistance) selectedDistance.textContent = 'Jarak: Izin lokasi ditolak. Gunakan tombol rute manual.';
                setLocateButtonState(false, false);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 300000
            }
        );
    }

    async function initMarkers() {
        for (const location of locations) {
            if (!(Number.isFinite(location.lat) && Number.isFinite(location.lng))) {
                const geocoded = await geocodeLocation(location);
                if (geocoded) {
                    location.lat = geocoded.lat;
                    location.lng = geocoded.lng;
                }
            }

            if (!(Number.isFinite(location.lat) && Number.isFinite(location.lng))) {
                continue;
            }

            const marker = window.L.marker([location.lat, location.lng]).addTo(map);
            marker.bindPopup(buildPopupHtml(location));

            marker.on('click', function () {
                selectedLocationId = location.id;
                updateSelectedInfo();
                renderLocationList();
                scrollSelectedCardIntoView();
            });

            markerById.set(location.id, marker);
            locationById.set(location.id, location);
        }

        const locationWithCoords = getSelectedLocation();
        if (Number.isFinite(locationWithCoords.lat) && Number.isFinite(locationWithCoords.lng)) {
            focusOnLocation(locationWithCoords, 12);
        }
    }

    listElement.addEventListener('click', function (event) {
        const card = event.target.closest('[data-location-id]');
        if (!card) {
            return;
        }

        const locationId = Number(card.getAttribute('data-location-id'));
        const location = locations.find(function (item) {
            return item.id === locationId;
        });

        if (!location) {
            return;
        }

        selectedLocationId = location.id;
        updateSelectedInfo();
        renderLocationList();
        scrollSelectedCardIntoView();

        if (Number.isFinite(location.lat) && Number.isFinite(location.lng)) {
            focusOnLocation(location, 14);
        }
    });

    if (carouselPrevButton) {
        carouselPrevButton.addEventListener('click', function () {
            scrollCarousel(-1);
        });
    }

    if (carouselNextButton) {
        carouselNextButton.addEventListener('click', function () {
            scrollCarousel(1);
        });
    }

    if (selectedGetRoute) {
        selectedGetRoute.addEventListener('click', function () {
            const location = getSelectedLocation();
            if (!(Number.isFinite(location.lat) && Number.isFinite(location.lng))) {
                window.open(buildMapsLink(location), '_blank', 'noopener');
                return;
            }

            const routeUrl = buildRouteLink(location, userCoords);
            window.open(routeUrl, '_blank', 'noopener');
        });
    }

    if (locateMeButton) {
        locateMeButton.addEventListener('click', requestUserLocation);
    }

    renderLocationList();
    updateSelectedInfo();
    setLocateButtonState(false, false);

    void initMarkers();

    setTimeout(function () {
        map.invalidateSize();
    }, 200);
}
