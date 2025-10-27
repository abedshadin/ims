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

        const submitButton = form.querySelector('[type="submit"]');
        const formData = new FormData(form);

        resetAlert();

        try {
            submitButton.disabled = true;

            const response = await fetch('store.php', {
                method: 'POST',
                body: formData,
            });

            if (response.status === 401) {
                const redirectTarget = `${window.location.pathname}${window.location.search}`;
                window.location.href = `../auth/login.php?redirect=${encodeURIComponent(redirectTarget)}`;
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

            showAlert(result.message, 'success');
            form.reset();
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
