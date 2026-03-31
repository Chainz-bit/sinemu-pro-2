function parseRegionData() {
    const node = document.getElementById('mapRegionData');
    if (!node) return {};

    try {
        const list = JSON.parse(node.textContent || '[]');
        return list.reduce(function (acc, region) {
            acc[region.slug] = region;
            return acc;
        }, {});
    } catch (error) {
        return {};
    }
}

function buildGoogleMapsUrl(lat, lng) {
    const latLng = encodeURIComponent(lat + ',' + lng);
    return 'https://www.google.com/maps/search/?api=1&query=' + latLng;
}

function isValidCoord(value) {
    return typeof value === 'number' && Number.isFinite(value);
}

function loadGeoCache() {
    try {
        return JSON.parse(localStorage.getItem('sinemu_geo_cache') || '{}');
    } catch (error) {
        return {};
    }
}

function saveGeoCache(cache) {
    try {
        localStorage.setItem('sinemu_geo_cache', JSON.stringify(cache));
    } catch (error) {
        // ignore storage failure
    }
}

async function geocodeRegion(region) {
    const cache = loadGeoCache();
    if (cache[region.slug]) {
        return cache[region.slug];
    }

    const q = encodeURIComponent(region.name + ', Indramayu, Jawa Barat, Indonesia');
    const url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=' + q;

    try {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json'
            }
        });
        if (!response.ok) return null;

        const result = await response.json();
        if (!Array.isArray(result) || result.length === 0) return null;

        const coords = {
            lat: Number(result[0].lat),
            lng: Number(result[0].lon)
        };

        if (!isValidCoord(coords.lat) || !isValidCoord(coords.lng)) return null;

        cache[region.slug] = coords;
        saveGeoCache(cache);
        return coords;
    } catch (error) {
        return null;
    }
}

export function initMap() {
    const mapRegionSelect = document.getElementById('mapRegionSelect');
    const adminRegionSelect = document.getElementById('adminRegionSelect');
    const activeRegionName = document.getElementById('activeRegionName');
    const mapRegionButtons = document.querySelectorAll('[data-map-region]');
    const mapServiceInputs = document.querySelectorAll('.map-service-input');
    const openGoogleMaps = document.getElementById('openGoogleMaps');
    const mapZoomIn = document.getElementById('mapZoomIn');
    const mapZoomOut = document.getElementById('mapZoomOut');
    const mapElement = document.getElementById('pickupMap');

    if (!mapElement) return;

    const mapRegions = parseRegionData();
    let leafletMap = null;
    let activeMarker = null;

    function initLeafletMap() {
        if (typeof window.L === 'undefined' || leafletMap) return;

        leafletMap = L.map(mapElement, {
            zoomControl: false,
            scrollWheelZoom: true
        }).setView([-6.3275, 108.3207], 12);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(leafletMap);

        setTimeout(function () {
            leafletMap.invalidateSize();
        }, 250);
    }

    async function resolveRegionCoords(region) {
        if (isValidCoord(region.lat) && isValidCoord(region.lng)) {
            return { lat: region.lat, lng: region.lng };
        }

        const geocoded = await geocodeRegion(region);
        if (geocoded) {
            return geocoded;
        }

        return { lat: -6.3275, lng: 108.3207 };
    }

    async function setActiveMapRegion(regionSlug) {
        const region = mapRegions[regionSlug];
        if (!region) return;

        initLeafletMap();
        const coords = await resolveRegionCoords(region);

        if (leafletMap) {
            leafletMap.setView([coords.lat, coords.lng], 14, { animate: true, duration: 0.35 });

            if (activeMarker) {
                leafletMap.removeLayer(activeMarker);
            }

            activeMarker = L.marker([coords.lat, coords.lng]).addTo(leafletMap);
            activeMarker.bindPopup('<strong>' + region.name + '</strong><br>Koordinat lokasi sudah disesuaikan.');
            activeMarker.on('click', function () {
                window.open(buildGoogleMapsUrl(coords.lat, coords.lng), '_blank', 'noopener');
            });
        }

        if (activeRegionName) {
            activeRegionName.textContent = region.name;
        }

        if (openGoogleMaps) {
            openGoogleMaps.href = buildGoogleMapsUrl(coords.lat, coords.lng);
        }

        if (mapRegionSelect && mapRegionSelect.value !== regionSlug) {
            mapRegionSelect.value = regionSlug;
        }
        if (adminRegionSelect && adminRegionSelect.value !== regionSlug) {
            adminRegionSelect.value = regionSlug;
        }

        mapRegionButtons.forEach(function (button) {
            button.classList.toggle('active', button.dataset.mapRegion === regionSlug);
        });
    }

    if (mapRegionSelect) {
        mapRegionSelect.addEventListener('change', function () {
            void setActiveMapRegion(this.value);
        });
    }

    if (adminRegionSelect) {
        adminRegionSelect.addEventListener('change', function () {
            void setActiveMapRegion(this.value);
        });
    }

    mapRegionButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            void setActiveMapRegion(this.dataset.mapRegion);
        });
    });

    mapServiceInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            mapServiceInputs.forEach(function (item) {
                const row = item.closest('.map-radio');
                if (!row) return;
                row.classList.toggle('active', item.checked);
            });
        });
    });

    const initialRegion = (adminRegionSelect && adminRegionSelect.value) || (mapRegionSelect && mapRegionSelect.value) || Object.keys(mapRegions)[0];
    if (initialRegion) {
        void setActiveMapRegion(initialRegion);
    }

    if (mapZoomIn) {
        mapZoomIn.addEventListener('click', function () {
            if (leafletMap) leafletMap.zoomIn();
        });
    }

    if (mapZoomOut) {
        mapZoomOut.addEventListener('click', function () {
            if (leafletMap) leafletMap.zoomOut();
        });
    }
}
