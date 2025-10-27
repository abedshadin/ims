document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('vendorForm');
    const alertBox = document.getElementById('formAlert');

    if (!form || !alertBox) {
        return;
    }

    const showAlert = (message, type = 'info') => {
        alertBox.textContent = message;
        alertBox.className = `alert alert-${type}`;
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submitButton = form.querySelector('[type="submit"]');
        const formData = new FormData(form);

        try {
            submitButton.disabled = true;

            const response = await fetch('store.php', {
                method: 'POST',
                body: formData,
            });

            const result = await response.json();

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
});
