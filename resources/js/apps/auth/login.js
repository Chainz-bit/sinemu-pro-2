(function () {
    const passwordInput = document.getElementById('password');
    const toggleButton = document.getElementById('togglePassword');
    const loginForm = document.querySelector('[data-login-form]');
    const submitButton = document.querySelector('[data-login-submit]');

    if (toggleButton && passwordInput) {
        toggleButton.addEventListener('click', function () {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            toggleButton.setAttribute('aria-label', isPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        });
    }

    if (loginForm && submitButton) {
        loginForm.addEventListener('submit', function () {
            submitButton.disabled = true;
            submitButton.textContent = submitButton.dataset.loadingLabel || 'Memproses...';
            loginForm.classList.add('is-submitting');
        });
    }
})();
