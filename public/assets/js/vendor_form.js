document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('vendorForm');
    const alertBox = document.getElementById('formAlert');

    if (!form || !alertBox) {
        return;
    }

    const resetAlert = () => {
        alertBox.textContent = '';
        alertBox.className = 'alert d-none';
    };

    const showAlert = (message, type = 'info') => {
        alertBox.textContent = message;
        alertBox.className = `alert alert-${type} mt-3`;
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submitButton = form.querySelector(
            'button[type="submit"], input[type="submit"], button:not([type])'
        );
        const formData = new FormData(form);
        const endpoint = form.dataset.endpoint || form.getAttribute('action') || 'store.php';
        const redirectTarget = form.dataset.redirect || '';
        const resetOnSuccess = form.dataset.resetOnSuccess !== 'false';
        const openFileUrl = form.dataset.openFileUrl || '';
        const autoFileInput = form.querySelector('[data-auto-file-name]');

        resetAlert();

        try {
            if (submitButton) {
                submitButton.disabled = true;
            }

            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData,
            });

            if (response.status === 401) {
                const loginRedirect = `${window.location.pathname}${window.location.search}`;
                window.location.href = `../auth/login.php?redirect=${encodeURIComponent(loginRedirect)}`;
                return;
            }

            let result;

            try {
                result = await response.json();
            } catch (error) {
                throw new Error('Unexpected response from the server.');
            }

            if (!response.ok || result.status !== 'success') {
                throw new Error(result.message || 'Unable to save vendor information.');
            }

            const successMessage = result.file_name
                ? `${result.message} Reference: ${result.file_name}.`
                : result.message;

            showAlert(successMessage, 'success');

            if (redirectTarget) {
                window.location.href = redirectTarget;
                return;
            }

            if (typeof result.redirect === 'string' && result.redirect.length > 0) {
                window.location.href = result.redirect;
                return;
            }

            if (openFileUrl && typeof result.file_token === 'string' && result.file_token.length > 0) {
                window.location.href = `${openFileUrl}${encodeURIComponent(result.file_token)}`;
                return;
            }

            if (resetOnSuccess) {
                const nextAutoValue = result.next_file_name || result.file_name || (autoFileInput ? autoFileInput.value : '');
                form.reset();

                if (autoFileInput && nextAutoValue) {
                    autoFileInput.value = nextAutoValue;
                }
            } else if (autoFileInput && (result.next_file_name || result.file_name)) {
                autoFileInput.value = result.next_file_name || result.file_name;
            }
        } catch (error) {
            showAlert(error.message, 'danger');
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    });

    form.addEventListener('input', () => {
        if (!alertBox.classList.contains('d-none')) {
            resetAlert();
        }
    });
});
