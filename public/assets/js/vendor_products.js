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

        const submitButton = form.querySelector(
            'button[type="submit"], input[type="submit"], button:not([type])'
        );
        const endpoint = form.dataset.endpoint || form.getAttribute('action') || 'products_store.php';
        const redirectTarget = form.dataset.redirect || '';
        const resetOnSuccess = form.dataset.resetOnSuccess !== 'false';
        const formData = new FormData(form);

        resetAlert();

        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            showAlert('Please fill in all required fields before submitting.', 'warning');
            return;
        }

        form.classList.remove('was-validated');

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
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    });

    const handleDeleteClick = async (button) => {
        const endpoint = button.dataset.endpoint || 'products_delete.php';
        const vendorId = button.dataset.vendorId || '';
        const productId = button.dataset.productId || '';
        const productName = button.dataset.productName || 'this product';

        resetAlert();

        if (!vendorId || !productId) {
            showAlert('Unable to delete this product because identifying details are missing.', 'danger');
            return;
        }

        const confirmed = window.confirm(`Are you sure you want to delete "${productName}"? This action cannot be undone.`);

        if (!confirmed) {
            return;
        }

        const payload = new FormData();
        payload.append('vendor_id', vendorId);
        payload.append('product_id', productId);

        button.disabled = true;

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                body: payload,
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
                throw new Error(result.message || 'Unable to delete the selected product.');
            }

            showAlert(result.message, 'success');

            const tableRow = button.closest('tr');

            if (tableRow) {
                tableRow.remove();
            }

            const tableBody = document.querySelector('#existingProducts tbody');

            if (tableBody && tableBody.children.length === 0) {
                const placeholderRow = document.createElement('tr');
                const cell = document.createElement('td');
                cell.colSpan = 12;
                cell.className = 'text-center text-muted py-4';
                cell.textContent = 'No products have been added for this vendor yet.';
                placeholderRow.appendChild(cell);
                tableBody.appendChild(placeholderRow);
            }
        } catch (error) {
            showAlert(error.message, 'danger');
        } finally {
            button.disabled = false;
        }
    };

    const deleteButtons = document.querySelectorAll('.js-delete-product');

    deleteButtons.forEach((button) => {
        button.addEventListener('click', () => {
            handleDeleteClick(button);
        });
    });

    form.addEventListener('input', () => {
        if (!alertBox.classList.contains('d-none')) {
            resetAlert();
        }

        if (form.classList.contains('was-validated')) {
            form.classList.remove('was-validated');
        }
    });
});
