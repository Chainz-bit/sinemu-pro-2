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
        monthSelectorType: 'dropdown'
    });
}

export function initFilterAndCounts() {
    const keywordInput = document.getElementById('keywordInput');
    const categorySelect = document.getElementById('categorySelect');
    const categoryDropdown = document.getElementById('categoryDropdown');
    const categoryDropdownToggle = document.getElementById('categoryDropdownToggle');
    const categoryDropdownMenu = document.getElementById('categoryDropdownMenu');
    const dateInput = document.getElementById('dateInput');
    const regionSelect = document.getElementById('regionSelect');
    const regionDropdown = document.getElementById('regionDropdown');
    const regionDropdownToggle = document.getElementById('regionDropdownToggle');
    const regionDropdownMenu = document.getElementById('regionDropdownMenu');
    const filterForm = document.getElementById('filterForm');

    const groups = {
        lost: Array.from(document.querySelectorAll('[data-list="lost"]')),
        found: Array.from(document.querySelectorAll('[data-list="found"]'))
    };
    const dropdownRoots = Array.from(document.querySelectorAll('.filter-dropdown'));

    initModernDatepicker(dateInput);

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

    function initCustomDropdown(dropdown, toggle, menu, select) {
        if (!dropdown || !toggle || !menu || !select) return;

        function closeAllDropdowns() {
            dropdownRoots.forEach(function (dropdownRoot) {
                dropdownRoot.classList.remove('open');
                const dropdownToggle = dropdownRoot.querySelector('.filter-dropdown-toggle');
                if (dropdownToggle) {
                    dropdownToggle.setAttribute('aria-expanded', 'false');
                }
            });
        }

        function closeDropdown() {
            dropdown.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        }

        function setActiveOption(value) {
            const options = menu.querySelectorAll('.filter-option');
            options.forEach(function (option) {
                option.classList.toggle('is-active', option.dataset.value === value);
            });
        }

        toggle.addEventListener('click', function () {
            const willOpen = !dropdown.classList.contains('open');
            closeAllDropdowns();
            if (willOpen) {
                dropdown.classList.add('open');
                toggle.setAttribute('aria-expanded', 'true');
                return;
            }
            toggle.setAttribute('aria-expanded', 'false');
        });

        menu.addEventListener('click', function (event) {
            const optionButton = event.target.closest('.filter-option');
            if (!optionButton) return;

            const value = optionButton.dataset.value || '';
            select.value = value;
            toggle.textContent = value;
            setActiveOption(value);
            closeDropdown();
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });

        document.addEventListener('click', function (event) {
            if (!dropdown.contains(event.target)) {
                closeDropdown();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeDropdown();
            }
        });

        const initialValue = select.value || toggle.textContent.trim();
        toggle.textContent = initialValue;
        setActiveOption(initialValue);
    }

    initCustomDropdown(categoryDropdown, categoryDropdownToggle, categoryDropdownMenu, categorySelect);
    initCustomDropdown(regionDropdown, regionDropdownToggle, regionDropdownMenu, regionSelect);

    if (filterForm) {
        filterForm.addEventListener('submit', function (event) {
            event.preventDefault();
            applyFilters();
        });
    }
}
