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

function toItemDateString(dateValue) {
    const raw = String(dateValue || '').trim();
    if (!raw) {
        return '';
    }

    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
        const parts = raw.split('-');
        return parts[1] + '/' + parts[2] + '/' + parts[0];
    }

    if (/^\d{2}\/\d{2}\/\d{4}$/.test(raw)) {
        return raw;
    }

    const parsed = new Date(raw);
    if (Number.isNaN(parsed.getTime())) {
        return '';
    }

    const month = String(parsed.getMonth() + 1).padStart(2, '0');
    const day = String(parsed.getDate()).padStart(2, '0');
    const year = parsed.getFullYear();
    return month + '/' + day + '/' + year;
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

function initCustomFilterDropdowns() {
    const dropdowns = Array.from(document.querySelectorAll('[data-filter-dropdown]'));
    if (dropdowns.length === 0) return;

    function closeDropdown(dropdown) {
        dropdown.classList.remove('open');
        const toggle = dropdown.querySelector('[data-filter-dropdown-toggle]');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
    }

    function closeOthers(activeDropdown) {
        dropdowns.forEach(function (dropdown) {
            if (dropdown !== activeDropdown) closeDropdown(dropdown);
        });
    }

    dropdowns.forEach(function (dropdown) {
        const select = dropdown.querySelector('select');
        const toggle = dropdown.querySelector('[data-filter-dropdown-toggle]');
        const label = dropdown.querySelector('[data-filter-dropdown-label]');
        const options = Array.from(dropdown.querySelectorAll('[data-filter-value]'));

        if (!select || !toggle || !label) return;

        toggle.setAttribute('aria-haspopup', 'listbox');
        toggle.setAttribute('aria-expanded', 'false');

        toggle.addEventListener('click', function () {
            const isOpen = dropdown.classList.contains('open');
            closeOthers(dropdown);
            dropdown.classList.toggle('open', !isOpen);
            toggle.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');
        });

        options.forEach(function (option) {
            option.addEventListener('click', function () {
                const value = option.dataset.filterValue || '';
                select.value = value;
                label.textContent = option.textContent.trim();
                options.forEach((item) => item.classList.toggle('is-active', item === option));
                closeDropdown(dropdown);
            });
        });
    });

    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-filter-dropdown]')) return;
        dropdowns.forEach(closeDropdown);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        dropdowns.forEach(closeDropdown);
    });
}

export function initFilterAndCounts() {
    const keywordInput = document.getElementById('keywordInput');
    const categorySelect = document.getElementById('categorySelect');
    const dateInput = document.getElementById('dateInput');
    const regionSelect = document.getElementById('regionSelect');
    const filterForm = document.getElementById('filterForm');
    const filterWrap = document.querySelector('.filter-wrap');

    const groups = {
        lost: Array.from(document.querySelectorAll('[data-list="lost"]')),
        found: Array.from(document.querySelectorAll('[data-list="found"]'))
    };

    initModernDatepicker(dateInput);
    initFilterPanelToggle(filterWrap, filterForm);
    initCustomFilterDropdowns();

    function applyFilters() {
        if (!keywordInput || !categorySelect || !dateInput || !regionSelect) return;

        const keyword = normalizeText(keywordInput.value);
        const category = normalizeCategory(categorySelect.value);
        const selectedDate = toItemDateString(dateInput.value);
        const selectedRegion = normalizeText(regionSelect.value);

        Object.entries(groups).forEach(function ([groupName, items]) {
            let visibleCount = 0;

            items.forEach(function (item) {
                const itemName = normalizeText(item.dataset.name);
                const itemCategory = String(item.dataset.category || '').trim().toUpperCase();
                const itemRegion = normalizeText(item.dataset.region);
                const itemDate = toItemDateString(item.dataset.date);

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
