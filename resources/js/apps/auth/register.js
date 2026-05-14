(function () {
    const toggleButtons = document.querySelectorAll('[data-toggle-target]');
    const registerForm = document.querySelector('[data-register-form]');
    const submitButton = document.querySelector('[data-register-submit]');

    toggleButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const targetId = button.getAttribute('data-toggle-target');
            if (!targetId) return;

            const input = document.getElementById(targetId);
            if (!input) return;

            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            button.setAttribute('aria-label', isPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        });
    });

    if (registerForm && submitButton) {
        registerForm.addEventListener('submit', function () {
            submitButton.disabled = true;
            submitButton.textContent = submitButton.dataset.loadingLabel || 'Mendaftarkan...';
            registerForm.classList.add('is-submitting');
        });
    }
})();
