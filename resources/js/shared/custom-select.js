(function () {
    const SELECTOR = 'select[data-custom-select]';
    const ENHANCED_KEY = 'sinemuSelectEnhanced';
    const states = new WeakMap();
    let activeState = null;
    let counter = 0;

    function normalizeLabel(text) {
        return (text || '').replace(/\s+/g, ' ').trim();
    }

    function selectedOption(select) {
        return select.options[select.selectedIndex] || select.querySelector('option:not([disabled])') || select.options[0] || null;
    }

    function optionLabel(option) {
        return normalizeLabel(option ? option.textContent : '');
    }

    function updateLabel(state) {
        const option = selectedOption(state.select);
        const label = optionLabel(option) || state.select.getAttribute('placeholder') || 'Pilih';

        state.label.textContent = label;
        state.button.disabled = state.select.disabled;
        state.button.classList.toggle('is-placeholder', !option || option.value === '');
    }

    function menuIdFor(select) {
        if (select.id) {
            return `${select.id}-sinemu-select-menu`;
        }

        counter += 1;
        return `sinemu-select-menu-${counter}`;
    }

    function markWrapper(state) {
        state.wrapper.classList.toggle('sinemu-select--filter', state.select.classList.contains('filter-btn'));
        state.wrapper.classList.toggle('sinemu-select--full', state.select.classList.contains('w-100'));
    }

    function createButton(select) {
        const button = document.createElement('button');
        const label = document.createElement('span');
        const chevron = document.createElement('span');

        button.type = 'button';
        button.className = ['sinemu-select__button']
            .concat(Array.from(select.classList).filter((className) => className !== 'sinemu-select__native'))
            .join(' ');
        button.setAttribute('aria-haspopup', 'listbox');
        button.setAttribute('aria-expanded', 'false');

        label.className = 'sinemu-select__label';
        chevron.className = 'sinemu-select__chevron';
        chevron.setAttribute('aria-hidden', 'true');

        button.append(label, chevron);

        return { button, label };
    }

    function createMenu(select) {
        const menu = document.createElement('div');
        menu.className = 'sinemu-select__menu';
        menu.id = menuIdFor(select);
        menu.hidden = true;
        menu.setAttribute('role', 'listbox');
        document.body.appendChild(menu);

        return menu;
    }

    function positionMenu(state) {
        if (state.menu.hidden) return;

        const rect = state.button.getBoundingClientRect();
        const margin = 8;
        const gap = 6;
        const top = rect.bottom + gap;
        const availableBelow = window.innerHeight - top - margin;
        const width = Math.min(rect.width, window.innerWidth - (margin * 2));
        const left = Math.min(Math.max(rect.left, margin), Math.max(margin, window.innerWidth - width - margin));
        const maxHeight = Math.max(96, Math.min(240, availableBelow));

        state.menu.style.top = `${top}px`;
        state.menu.style.left = `${left}px`;
        state.menu.style.width = `${width}px`;
        state.menu.style.maxHeight = `${maxHeight}px`;
    }

    function closeSelect(state, focusButton) {
        if (!state || state.menu.hidden) return;

        state.menu.hidden = true;
        state.wrapper.classList.remove('is-open');
        state.button.setAttribute('aria-expanded', 'false');

        if (activeState === state) {
            activeState = null;
        }

        if (focusButton) {
            state.button.focus();
        }
    }

    function closeActive(focusButton) {
        closeSelect(activeState, focusButton);
    }

    function dispatchNativeChange(select) {
        select.dispatchEvent(new Event('input', { bubbles: true }));
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function selectOption(state, option) {
        if (!option || option.disabled) return;

        state.select.value = option.value;
        updateLabel(state);
        renderOptions(state);
        dispatchNativeChange(state.select);
        closeSelect(state, true);
    }

    function focusSiblingOption(menu, current, offset) {
        const options = Array.from(menu.querySelectorAll('.sinemu-select__option:not(:disabled)'));
        const index = options.indexOf(current);
        const next = options[index + offset] || options[index] || options[0];

        if (next) {
            next.focus();
        }
    }

    function optionKeydown(event, state, option) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            selectOption(state, option);
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            focusSiblingOption(state.menu, event.currentTarget, 1);
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            focusSiblingOption(state.menu, event.currentTarget, -1);
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeSelect(state, true);
        }
    }

    function appendOptionButton(state, option) {
        const item = document.createElement('button');
        const isSelected = option.selected;

        item.type = 'button';
        item.className = 'sinemu-select__option';
        item.textContent = optionLabel(option);
        item.disabled = option.disabled;
        item.dataset.value = option.value;
        item.setAttribute('role', 'option');
        item.setAttribute('aria-selected', isSelected ? 'true' : 'false');
        item.classList.toggle('is-active', isSelected);
        item.addEventListener('click', () => selectOption(state, option));
        item.addEventListener('keydown', (event) => optionKeydown(event, state, option));

        state.menu.appendChild(item);
    }

    function appendOptionGroup(state, group) {
        const label = document.createElement('div');
        label.className = 'sinemu-select__group';
        label.textContent = group.label;
        state.menu.appendChild(label);

        Array.from(group.children)
            .filter((child) => child.tagName === 'OPTION')
            .forEach((option) => appendOptionButton(state, option));
    }

    function renderOptions(state) {
        state.menu.replaceChildren();

        Array.from(state.select.children).forEach((child) => {
            if (child.tagName === 'OPTGROUP') {
                appendOptionGroup(state, child);
                return;
            }

            if (child.tagName === 'OPTION') {
                appendOptionButton(state, child);
            }
        });
    }

    function openSelect(state) {
        if (state.select.disabled) return;

        if (activeState && activeState !== state) {
            closeSelect(activeState, false);
        }

        activeState = state;
        renderOptions(state);
        state.menu.hidden = false;
        state.wrapper.classList.add('is-open');
        state.button.setAttribute('aria-expanded', 'true');
        positionMenu(state);

        const activeOption = state.menu.querySelector('.sinemu-select__option.is-active:not(:disabled)')
            || state.menu.querySelector('.sinemu-select__option:not(:disabled)');
        if (activeOption) {
            activeOption.scrollIntoView({ block: 'nearest' });
        }
    }

    function toggleSelect(state) {
        if (state.menu.hidden) {
            openSelect(state);
            return;
        }

        closeSelect(state, false);
    }

    function buttonKeydown(event, state) {
        if (event.key === 'Enter' || event.key === ' ' || event.key === 'ArrowDown') {
            event.preventDefault();
            openSelect(state);

            const first = state.menu.querySelector('.sinemu-select__option.is-active:not(:disabled)')
                || state.menu.querySelector('.sinemu-select__option:not(:disabled)');
            if (first) {
                first.focus();
            }
            return;
        }

        if (event.key === 'Escape') {
            closeSelect(state, false);
        }
    }

    function observeSelect(state) {
        const observer = new MutationObserver(() => {
            markWrapper(state);
            updateLabel(state);
            if (!state.menu.hidden) {
                renderOptions(state);
                positionMenu(state);
            }
        });

        observer.observe(state.select, {
            attributes: true,
            attributeFilter: ['class', 'disabled'],
            childList: true,
            subtree: true,
        });

        state.observer = observer;
    }

    function enhanceSelect(select) {
        if (!select || select.dataset[ENHANCED_KEY] === 'true' || select.multiple) {
            return;
        }

        const wrapper = document.createElement('span');
        const { button, label } = createButton(select);
        const menu = createMenu(select);

        if (select.closest('#filterForm')) {
            menu.classList.add('sinemu-select__menu--quick-filter');
        }

        wrapper.className = 'sinemu-select';
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);
        wrapper.appendChild(button);
        select.classList.add('sinemu-select__native');
        select.tabIndex = -1;
        select.dataset[ENHANCED_KEY] = 'true';

        const state = { select, wrapper, button, label, menu, observer: null };
        states.set(select, state);

        button.setAttribute('aria-controls', menu.id);
        markWrapper(state);
        updateLabel(state);
        observeSelect(state);

        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            toggleSelect(state);
        });
        button.addEventListener('keydown', (event) => buttonKeydown(event, state));
        select.addEventListener('change', () => {
            button.classList.remove('is-invalid');
            updateLabel(state);
        });
        select.addEventListener('invalid', () => {
            button.classList.add('is-invalid');
        });
        select.addEventListener('input', () => {
            button.classList.remove('is-invalid');
        });
    }

    function enhanceAll(root) {
        (root || document).querySelectorAll(SELECTOR).forEach(enhanceSelect);
    }

    document.addEventListener('pointerdown', (event) => {
        if (!activeState) return;
        if (activeState.wrapper.contains(event.target) || activeState.menu.contains(event.target)) return;

        closeActive(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeActive(true);
        }
    });

    window.addEventListener('resize', () => {
        if (activeState) {
            positionMenu(activeState);
        }
    });

    window.addEventListener('scroll', () => {
        if (activeState) {
            positionMenu(activeState);
        }
    }, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => enhanceAll(document));
    } else {
        enhanceAll(document);
    }

    window.SiNemuCustomSelect = {
        refresh: enhanceAll,
    };
})();
