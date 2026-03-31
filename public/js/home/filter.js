function normalizeCategory(value) {
    const map = {
        'semua kategori': '',
        elektronik: 'GADGET',
        hewan: 'HEWAN',
        otomotif: 'OTOMOTIF',
        dokumen: 'DOKUMEN',
        aksesoris: 'AKSESORIS',
        gadget: 'GADGET'
    };
    return map[value.toLowerCase()] || value.toUpperCase();
}

function formatDate(dateValue) {
    const date = new Date(dateValue);
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const year = date.getFullYear();
    return month + '/' + day + '/' + year;
}

function updateCountText(groupName, count) {
    const countEl = document.getElementById(groupName + 'CountText');
    const emptyEl = document.getElementById(groupName + 'EmptyState');

    if (countEl) {
        countEl.textContent = count + ' item';
    }
    if (emptyEl) {
        emptyEl.style.display = count === 0 ? 'block' : 'none';
    }
}

export function initFilterAndCounts() {
    const keywordInput = document.getElementById('keywordInput');
    const categorySelect = document.getElementById('categorySelect');
    const dateInput = document.getElementById('dateInput');
    const regionSelect = document.getElementById('regionSelect');
    const filterForm = document.getElementById('filterForm');

    const groups = {
        lost: Array.from(document.querySelectorAll('[data-list="lost"]')),
        found: Array.from(document.querySelectorAll('[data-list="found"]'))
    };

    function applyFilters() {
        if (!keywordInput || !categorySelect || !dateInput || !regionSelect) return;

        const keyword = keywordInput.value.trim().toLowerCase();
        const category = normalizeCategory(categorySelect.value);
        const selectedDate = dateInput.value;
        const selectedRegion = regionSelect.value.toLowerCase();

        Object.entries(groups).forEach(function ([groupName, items]) {
            let visibleCount = 0;

            items.forEach(function (item) {
                const itemName = item.dataset.name;
                const itemCategory = item.dataset.category;
                const itemRegion = item.dataset.region;
                const itemDate = item.dataset.date;

                const matchKeyword = !keyword || itemName.includes(keyword) || itemCategory.toLowerCase().includes(keyword);
                const matchCategory = !category || itemCategory === category;
                const matchDate = !selectedDate || itemDate === formatDate(selectedDate);
                const matchRegion = selectedRegion === 'seluruh wilayah' || !selectedRegion || itemRegion.includes(selectedRegion);
                const visible = matchKeyword && matchCategory && matchDate && matchRegion;

                item.style.display = visible ? '' : 'none';
                if (visible) visibleCount += 1;
            });

            updateCountText(groupName, visibleCount);
        });
    }

    Object.entries(groups).forEach(function ([groupName, items]) {
        updateCountText(groupName, items.length);
    });

    if (keywordInput && categorySelect && dateInput && regionSelect) {
        keywordInput.addEventListener('input', applyFilters);
        categorySelect.addEventListener('change', applyFilters);
        dateInput.addEventListener('change', applyFilters);
        regionSelect.addEventListener('change', applyFilters);
    }

    if (filterForm) {
        filterForm.addEventListener('submit', function (event) {
            event.preventDefault();
            applyFilters();
        });
    }
}
