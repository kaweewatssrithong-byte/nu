document.addEventListener('DOMContentLoaded', () => {
    const password = document.getElementById('password');
    const username = document.getElementById('username');
    const toggle = document.getElementById('togglePassword');
    const form = document.getElementById('loginForm');
    const submitButton = form?.querySelector('.submit-button');
    const rememberUsername = document.getElementById('rememberUsername');
    const usernameKey = 'workflow_login_username';

    try {
        const savedUsername = localStorage.getItem(usernameKey);
        if (savedUsername && !username.value) username.value = savedUsername;
        if (rememberUsername) rememberUsername.checked = Boolean(savedUsername);
    } catch (_) {
        // Login still works when browser storage is disabled.
    }

    toggle?.addEventListener('click', () => {
        const isHidden = password.type === 'password';
        password.type = isHidden ? 'text' : 'password';
        toggle.setAttribute('aria-label', isHidden ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน');
    });

    form?.addEventListener('submit', (event) => {
        if (!username.value.trim() || !password.value) {
            event.preventDefault();
            const missing = !username.value.trim() ? username : password;
            missing.setAttribute('aria-invalid', 'true');
            missing.closest('.input-wrap')?.classList.add('field-invalid');
            missing.focus();
            return;
        }

        try {
            if (rememberUsername?.checked) localStorage.setItem(usernameKey, username.value.trim());
            else localStorage.removeItem(usernameKey);
        } catch (_) {
            // Do not block sign-in when browser storage is disabled.
        }

        submitButton?.classList.add('is-loading');
        submitButton?.setAttribute('aria-busy', 'true');
        if (submitButton) submitButton.disabled = true;
    });

    form?.querySelectorAll('input').forEach((input) => {
        input.addEventListener('input', () => {
            input.removeAttribute('aria-invalid');
            input.closest('.input-wrap')?.classList.remove('field-invalid');
        });
    });
});
