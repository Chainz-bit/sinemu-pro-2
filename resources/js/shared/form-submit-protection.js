/**
 * Reusable Form Double-Submit Protection Module
 * 
 * Features:
 * - Prevents multiple form submissions on slow networks
 * - Disables submit button immediately and shows loading spinner + text
 * - Restores button state if HTML5 validation fails or event is cancelled
 * - Restores button state when user returns via browser back/forward (bfcache)
 */

const SPINNER_STYLES = `
@keyframes sinemu-btn-spin {
    to { transform: rotate(360deg); }
}
.sinemu-spinner {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    vertical-align: -0.15em;
    border: 2px solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: sinemu-btn-spin 0.7s linear infinite;
    margin-right: 0.5rem;
    box-sizing: border-box;
}
`;

function injectStyles() {
    if (typeof document === 'undefined' || document.getElementById('sinemu-submit-protection-styles')) {
        return;
    }
    const style = document.createElement('style');
    style.id = 'sinemu-submit-protection-styles';
    style.textContent = SPINNER_STYLES;
    document.head.appendChild(style);
}

export function protectForm(form, customLoadingText) {
    if (!form || form.__sinemuSubmitProtected) {
        return;
    }
    form.__sinemuSubmitProtected = true;
    injectStyles();

    const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
    if (!submitBtn) {
        return;
    }

    const defaultText = customLoadingText
        || submitBtn.getAttribute('data-loading-text')
        || (form.action && form.action.includes('claim') ? 'Memproses Klaim...' : 'Menyimpan Laporan...');

    function restoreButton() {
        form.dataset.submitting = 'false';
        if (submitBtn.dataset.originalHtml) {
            submitBtn.innerHTML = submitBtn.dataset.originalHtml;
            delete submitBtn.dataset.originalHtml;
        }
        submitBtn.disabled = submitBtn.dataset.initiallyDisabled === 'true';
        submitBtn.style.pointerEvents = '';
        delete submitBtn.dataset.initiallyDisabled;
    }

    // Restore if HTML5 validation fails on any field
    form.addEventListener('invalid', function () {
        restoreButton();
    }, true);

    form.addEventListener('submit', function (event) {
        // If client-side validation failed
        if (!form.noValidate && typeof form.checkValidity === 'function' && !form.checkValidity()) {
            restoreButton();
            return;
        }

        if (event.defaultPrevented) {
            restoreButton();
            return;
        }

        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            event.stopPropagation();
            return;
        }

        form.dataset.submitting = 'true';
        submitBtn.dataset.initiallyDisabled = submitBtn.disabled ? 'true' : 'false';
        submitBtn.dataset.originalHtml = submitBtn.innerHTML;

        const loadingText = submitBtn.getAttribute('data-loading-text') || defaultText;
        submitBtn.innerHTML = `<span class="sinemu-spinner" aria-hidden="true"></span>${loadingText}`;
        submitBtn.style.pointerEvents = 'none';

        // Disable asynchronously so standard form POST triggers cleanly
        setTimeout(function () {
            submitBtn.disabled = true;
        }, 0);
    });

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            restoreButton();
        }
    });

    form.restoreSubmitButton = restoreButton;
}

export function initFormSubmitProtection(selector = 'form.input-form, form[data-protect-double-submit]') {
    if (typeof document === 'undefined') return;
    document.querySelectorAll(selector).forEach(function (form) {
        protectForm(form);
    });
}

// Auto-initialize when DOM is ready
if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initFormSubmitProtection();
        });
    } else {
        initFormSubmitProtection();
    }
}
