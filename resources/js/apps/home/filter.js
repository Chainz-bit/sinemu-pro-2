function normalizeText(value) {
    return String(value || '')
        .trim()
        .toLowerCase();
}

function normalizeCategory(value) {
    const normalized = normalizeText(value);
    if (!normalized || normalized === 'semua kategori') {
        return '';
    }

    return normalized.toUpperCase();
}

function toDateKey(dateValue) {
    const raw = String(dateValue || '').trim();
    if (!raw) {
        return '';
    }

    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
        return raw;
    }

    if (/^\d{2}\/\d{2}\/\d{4}$/.test(raw)) {
        const parts = raw.split('/');
        const first = Number.parseInt(parts[0], 10);
        const second = Number.parseInt(parts[1], 10);

        if (first > 12) {
            return parts[2] + '-' + parts[1] + '-' + parts[0];
        }

        if (second > 12) {
            return parts[2] + '-' + parts[0] + '-' + parts[1];
        }

        // Placeholder dan flatpickr lokal memakai dd/mm/yyyy.
        return parts[2] + '-' + parts[1] + '-' + parts[0];
    }

    const parsed = new Date(raw);
    if (Number.isNaN(parsed.getTime())) {
        return '';
    }

    const month = String(parsed.getMonth() + 1).padStart(2, '0');
    const day = String(parsed.getDate()).padStart(2, '0');
    const year = parsed.getFullYear();
    return year + '-' + month + '-' + day;
}

function isInvalidDateInput(dateInput) {
    const visibleDateInput = dateInput?._flatpickr?.altInput || dateInput;
    const raw = String(visibleDateInput?.value || dateInput?.value || '').trim();

    return raw !== '' && toDateKey(raw) === '';
}

function updateCountText(groupName, count) {
    const countEl = document.getElementById(groupName + 'CountText');
    const emptyEl = document.getElementById(groupName + 'EmptyState');
    const listEl = document.getElementById(groupName + 'ItemsList');

    if (countEl) {
        countEl.textContent = count + ' item';
    }
    if (emptyEl) {
        emptyEl.style.display = count === 0 ? 'flex' : 'none';
    }
    if (listEl) {
        listEl.classList.toggle('is-single', count === 1);
    }
}

function getInitialDbCount(groupName, fallbackCount) {
    const countEl = document.getElementById(groupName + 'CountText');
    if (!countEl) return fallbackCount;

    const parsed = Number.parseInt(countEl.dataset.totalCount || '', 10);
    return Number.isFinite(parsed) ? parsed : fallbackCount;
}

function initModernDatepicker(dateInput) {
    if (!dateInput || typeof window.flatpickr !== 'function') return;

    const locale = (window.flatpickr.l10ns && window.flatpickr.l10ns.id) ? window.flatpickr.l10ns.id : 'default';
    window.flatpickr(dateInput, {
        locale: locale,
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd/m/Y',
        allowInput: true,
        disableMobile: true,
        animate: true,
        monthSelectorType: 'dropdown',
        position: 'below left',
        onReady: function (_selectedDates, _dateStr, instance) {
            if (!instance.altInput) return;
            instance.altInput.setAttribute('autocomplete', 'off');
            instance.altInput.setAttribute('autocorrect', 'off');
            instance.altInput.setAttribute('autocapitalize', 'off');
            instance.altInput.setAttribute('spellcheck', 'false');
        }
    });
}

function initFilterPanelToggle(filterWrap, filterForm) {
    if (!filterWrap || !filterForm) return;

    const toggleButton = filterWrap.querySelector('.chevron-btn');
    if (!toggleButton) return;

    const icon = toggleButton.querySelector('i');
    const collapseClass = 'is-collapsed';
    const panelId = filterForm.id || 'filterForm';

    toggleButton.setAttribute('aria-controls', panelId);

    function setCollapsed(collapsed) {
        filterWrap.classList.toggle(collapseClass, collapsed);
        toggleButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        toggleButton.setAttribute('aria-label', collapsed ? 'Buka panel filter' : 'Tutup panel filter');

        if (icon) {
            icon.classList.toggle('fa-chevron-up', !collapsed);
            icon.classList.toggle('fa-chevron-down', collapsed);
        }
    }

    setCollapsed(false);

    toggleButton.addEventListener('click', function () {
        setCollapsed(!filterWrap.classList.contains(collapseClass));
    });
}

export function initFilterAndCounts() {
    const keywordInput = document.getElementById('keywordInput');
    const categorySelect = document.getElementById('categorySelect');
    const dateInput = document.getElementById('dateInput');
    const regionSelect = document.getElementById('regionSelect');
    const filterForm = document.getElementById('filterForm');
    const filterWrap = document.querySelector('.filter-wrap');
    const filterFeedback = document.getElementById('filterFormFeedback');

    const groups = {
        lost: Array.from(document.querySelectorAll('[data-list="lost"]')),
        found: Array.from(document.querySelectorAll('[data-list="found"]'))
    };

    initModernDatepicker(dateInput);
    initFilterPanelToggle(filterWrap, filterForm);

    function applyFilters() {
        if (!keywordInput || !categorySelect || !dateInput || !regionSelect) return;

        const keyword = normalizeText(keywordInput.value);
        const category = normalizeCategory(categorySelect.value);
        if (isInvalidDateInput(dateInput)) {
            const visibleDateInput = dateInput?._flatpickr?.altInput || dateInput;
            visibleDateInput?.classList.add('is-invalid');
            if (filterFeedback) {
                filterFeedback.textContent = 'Format tanggal tidak valid. Gunakan format dd/mm/yyyy.';
            }
            return;
        }

        const visibleDateInput = dateInput?._flatpickr?.altInput || dateInput;
        visibleDateInput?.classList.remove('is-invalid');
        if (filterFeedback) {
            filterFeedback.textContent = '';
        }

        const selectedDate = toDateKey(dateInput.value || visibleDateInput?.value);
        const selectedRegion = normalizeText(regionSelect.value);

        Object.entries(groups).forEach(function ([groupName, items]) {
            let visibleCount = 0;

            items.forEach(function (item) {
                const itemName = normalizeText(item.dataset.name);
                const itemCategory = String(item.dataset.category || '').trim().toUpperCase();
                const itemRegion = normalizeText(item.dataset.region);
                const itemDate = toDateKey(item.dataset.date);

                const keywordHaystack = [itemName, itemCategory.toLowerCase(), itemRegion].join(' ');
                const matchKeyword = !keyword || keywordHaystack.includes(keyword);

                // Untuk data lama barang hilang yang kategorinya masih "UMUM",
                // jangan dipaksa strict supaya filter tetap terasa relevan.
                const isGenericLostCategory = groupName === 'lost' && itemCategory === 'UMUM';
                const matchCategory = !category || isGenericLostCategory || itemCategory === category;
                const matchDate = !selectedDate || itemDate === selectedDate;
                const matchRegion = selectedRegion === 'seluruh wilayah' || !selectedRegion || itemRegion.includes(selectedRegion);
                const visible = matchKeyword && matchCategory && matchDate && matchRegion;

                item.style.display = visible ? '' : 'none';
                if (visible) visibleCount += 1;
            });

            updateCountText(groupName, visibleCount);
        });
    }

    Object.entries(groups).forEach(function ([groupName, items]) {
        const dbCount = getInitialDbCount(groupName, items.length);
        updateCountText(groupName, dbCount);
    });

    // Filter dijalankan hanya ketika user submit form (klik tombol "Cari"),
    // bukan saat user mengetik atau mengubah pilihan dropdown.

    if (filterForm) {
        filterForm.addEventListener('submit', function (event) {
            event.preventDefault();
            applyFilters();
        });
    }

}
