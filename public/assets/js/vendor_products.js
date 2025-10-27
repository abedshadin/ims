document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('productForm');
    const alertBox = document.getElementById('productFormAlert');

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

        const submitButton = form.querySelector('[type="submit"]');
        const endpoint = form.dataset.endpoint || form.getAttribute('action') || 'products_store.php';
        const redirectTarget = form.dataset.redirect || '';
        const resetOnSuccess = form.dataset.resetOnSuccess !== 'false';
        const formData = new FormData(form);

        resetAlert();

        try {
            submitButton.disabled = true;

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
                throw new Error(result.message || 'Unable to save product information.');
            }

            showAlert(result.message, 'success');

            if (redirectTarget) {
                window.location.href = redirectTarget;
                return;
            }

            if (resetOnSuccess) {
                form.reset();
            }
        } catch (error) {
            showAlert(error.message, 'danger');
        } finally {
            submitButton.disabled = false;
        }
    });

    form.addEventListener('input', () => {
        if (!alertBox.classList.contains('d-none')) {
            resetAlert();
        }
    });
});
