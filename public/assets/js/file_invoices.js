/* global bootstrap */

document.addEventListener('DOMContentLoaded', () => {
    const dataElement = document.getElementById('fileInvoicesData');

    if (!dataElement) {
        return;
    }

    const parseJson = (element) => {
        try {
            return JSON.parse(element.textContent || '{}');
        } catch (error) {
            return {};
        }
    };

    const escapeSelector = (value) => {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }

        return value.replace(/[^a-zA-Z0-9_\-]/g, '\\$&');
    };

    const state = parseJson(dataElement);
    state.proformas = Array.isArray(state.proformas) ? state.proformas : [];
    state.vendorProducts = Array.isArray(state.vendorProducts) ? state.vendorProducts : [];

    const piList = document.getElementById('piList');
    const noPiMessage = document.getElementById('noPiMessage');
    const createPiForm = document.getElementById('createPiForm');
    const piAlert = document.getElementById('piAlert');
    const productModalElement = document.getElementById('productModal');
    const productForm = document.getElementById('productForm');
    const productFormSubmit = document.getElementById('productFormSubmit');
    const vendorProductSelect = document.getElementById('vendor_product_id');
    const productModeInputs = document.querySelectorAll('input[name="product_mode"]');
    const existingProductFields = document.getElementById('existingProductFields');
    const newProductFields = document.getElementById('newProductFields');
    const productAlertBox = document.getElementById('productFormAlert');
    const productPreview = document.getElementById('vendorProductPreview');
    const previewFields = productPreview ? productPreview.querySelectorAll('[data-preview]') : [];
    let activePiToken = null;
    let productModalInstance = null;

    const escapeHtml = (value) => {
        const div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    };

    const showAlert = (element, message, type = 'info') => {
        if (!element) {
            return;
        }

        element.textContent = message;
        element.className = `alert alert-${type}`;
    };

    const resetAlert = (element) => {
        if (!element) {
            return;
        }

        element.textContent = '';
        element.className = 'alert d-none';
    };

    const toCurrency = (value) => {
        const number = Number.parseFloat(value);
        if (Number.isNaN(number)) {
            return value;
        }

        return number.toFixed(2);
    };

    const populateVendorProductSelect = () => {
        if (!vendorProductSelect) {
            return;
        }

        const currentValue = vendorProductSelect.value;
        vendorProductSelect.innerHTML = '<option value="">Select a product</option>';

        state.vendorProducts
            .slice()
            .sort((a, b) => a.product_name.localeCompare(b.product_name))
            .forEach((product) => {
                if (!product.token) {
                    return;
                }

                const option = document.createElement('option');
                option.value = product.token;
                option.textContent = `${product.product_name} · ${product.brand}`;
                vendorProductSelect.append(option);
            });

        if (currentValue) {
            vendorProductSelect.value = currentValue;
        }
    };

    const findVendorProduct = (token) => {
        return state.vendorProducts.find((product) => product.token === token) || null;
    };

    const updateProductPreview = () => {
        if (!productPreview) {
            return;
        }

        const token = vendorProductSelect.value;
        const product = findVendorProduct(token);

        if (!product) {
            productPreview.classList.add('d-none');
            previewFields.forEach((field) => {
                field.textContent = '';
            });
            return;
        }

        previewFields.forEach((field) => {
            const key = field.getAttribute('data-preview');
            if (!key || !(key in product)) {
                field.textContent = '';
                return;
            }

            if (key === 'rate' || key === 'dec_unit_price' || key === 'asses_unit_price') {
                field.textContent = `$${toCurrency(product[key])}`;
            } else {
                field.textContent = product[key];
            }
        });

        productPreview.classList.remove('d-none');
    };

    const renderProductRow = (product) => {
        const row = document.createElement('tr');
        row.dataset.productToken = product.token || '';
        row.innerHTML = `
            <td>
                <div class="fw-semibold">${escapeHtml(product.product_name || '')}</div>
                <div class="text-muted small">${escapeHtml(product.brand || '')}</div>
            </td>
            <td>
                <div>${escapeHtml(product.product_category || '')}</div>
                <div class="text-muted small">COO: ${escapeHtml(product.country_of_origin || '')}</div>
            </td>
            <td>
                <div>${escapeHtml(product.product_size || '')}</div>
                <div class="text-muted small">Unit: ${escapeHtml(product.unit || '')}</div>
            </td>
            <td class="text-end">
                <div class="fw-semibold">$${escapeHtml(product.rate_formatted || toCurrency(product.rate || '0'))}</div>
                <div class="text-muted small">Weight: ${escapeHtml(product.item_weight || '')}</div>
            </td>
            <td class="text-end">
                <div>$${escapeHtml(product.dec_unit_price_formatted || toCurrency(product.dec_unit_price || '0'))}</div>
                <div class="text-muted small">HS: ${escapeHtml(product.hs_code || '')}</div>
            </td>
            <td class="text-end">
                <div>$${escapeHtml(product.asses_unit_price_formatted || toCurrency(product.asses_unit_price || '0'))}</div>
            </td>
        `;
        return row;
    };

    const renderProformaCard = (proforma) => {
        const card = document.createElement('div');
        card.className = 'card shadow-sm border-0 mb-4';
        card.dataset.piToken = proforma.token || '';

        const createdAt = proforma.created_at_human || '';

        card.innerHTML = `
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <h3 class="h5 mb-1">Proforma Invoice ${escapeHtml(proforma.invoice_number || '')}</h3>
                        <p class="text-muted small mb-0">Created ${escapeHtml(createdAt)}</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" type="button" data-action="add-product" data-pi-token="${escapeHtml(proforma.token || '')}">
                            Add Product
                        </button>
                    </div>
                </div>
                <div class="table-responsive mt-4">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Product</th>
                                <th scope="col">Category &amp; COO</th>
                                <th scope="col">Size &amp; Unit</th>
                                <th scope="col" class="text-end">Unit Rate</th>
                                <th scope="col" class="text-end">DEC &amp; HS</th>
                                <th scope="col" class="text-end">Assesment</th>
                            </tr>
                        </thead>
                        <tbody data-products-for="${escapeHtml(proforma.token || '')}"></tbody>
                    </table>
                </div>
                <p class="text-muted small mt-3 ${proforma.products && proforma.products.length ? 'd-none' : ''}" data-empty-state-for="${escapeHtml(proforma.token || '')}">
                    No products have been added to this proforma invoice yet.
                </p>
            </div>
        `;

        const tbody = card.querySelector(`[data-products-for="${escapeSelector(proforma.token || '')}"]`);

        if (tbody && Array.isArray(proforma.products)) {
            proforma.products.forEach((product) => {
                tbody.append(renderProductRow(product));
            });
        }

        return card;
    };

    const refreshPiList = () => {
        if (!piList) {
            return;
        }

        piList.innerHTML = '';

        state.proformas.forEach((proforma) => {
            piList.append(renderProformaCard(proforma));
        });

        if (noPiMessage) {
            if (state.proformas.length === 0) {
                noPiMessage.classList.remove('d-none');
            } else {
                noPiMessage.classList.add('d-none');
            }
        }
    };

    const openProductModal = (piToken) => {
        if (!productForm || !productModalElement) {
            return;
        }

        activePiToken = piToken;
        productForm.reset();
        resetAlert(productAlertBox);

        const piTokenInput = productForm.querySelector('#pi_token');
        if (piTokenInput) {
            piTokenInput.value = piToken;
        }

        populateVendorProductSelect();
        updateProductPreview();
        toggleProductMode();

        if (!productModalInstance) {
            productModalInstance = new bootstrap.Modal(productModalElement);
        }

        productModalInstance.show();
    };

    const toggleProductMode = () => {
        if (!existingProductFields || !newProductFields) {
            return;
        }

        const selected = document.querySelector('input[name="product_mode"]:checked');
        const mode = selected ? selected.value : 'existing';

        const manualFields = newProductFields.querySelectorAll('input, select');

        if (mode === 'new') {
            existingProductFields.classList.add('d-none');
            newProductFields.classList.remove('d-none');
            vendorProductSelect.required = false;
            manualFields.forEach((field) => {
                field.required = true;
            });
        } else {
            existingProductFields.classList.remove('d-none');
            newProductFields.classList.add('d-none');
            vendorProductSelect.required = true;
            manualFields.forEach((field) => {
                field.required = false;
                field.value = '';
            });
        }
    };

    const attachEventListeners = () => {
        if (createPiForm) {
            createPiForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                resetAlert(piAlert);

                const submitButton = createPiForm.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                }

                try {
                    const formData = new FormData(createPiForm);
                    const response = await fetch('proforma_store.php', {
                        method: 'POST',
                        body: formData,
                    });

                    if (response.status === 401) {
                        window.location.reload();
                        return;
                    }

                    let result;

                    try {
                        result = await response.json();
                    } catch (error) {
                        throw new Error('Unexpected response received.');
                    }

                    if (!response.ok || result.status !== 'success') {
                        throw new Error(result.message || 'Unable to add the proforma invoice.');
                    }

                    if (result.proforma) {
                        state.proformas.unshift(result.proforma);
                        refreshPiList();
                        createPiForm.reset();
                    }

                    showAlert(piAlert, result.message || 'Proforma invoice added.', 'success');
                } catch (error) {
                    showAlert(piAlert, error.message, 'danger');
                } finally {
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                }
            });
        }

        if (piList) {
            piList.addEventListener('click', (event) => {
                const button = event.target.closest('[data-action="add-product"]');
                if (!button) {
                    return;
                }

                const piToken = button.getAttribute('data-pi-token') || '';
                if (piToken) {
                    openProductModal(piToken);
                }
            });
        }

        if (productModeInputs) {
            productModeInputs.forEach((input) => {
                input.addEventListener('change', toggleProductMode);
            });
        }

        if (vendorProductSelect) {
            vendorProductSelect.addEventListener('change', updateProductPreview);
        }

        if (productFormSubmit && productForm) {
            productFormSubmit.addEventListener('click', async () => {
                resetAlert(productAlertBox);

                const submitButton = productFormSubmit;
                submitButton.disabled = true;

                try {
                    const formData = new FormData(productForm);

                    if (!activePiToken) {
                        throw new Error('Select a proforma invoice before adding products.');
                    }

                    formData.set('pi_token', activePiToken);

                    const response = await fetch('proforma_products_store.php', {
                        method: 'POST',
                        body: formData,
                    });

                    if (response.status === 401) {
                        window.location.reload();
                        return;
                    }

                    let result;

                    try {
                        result = await response.json();
                    } catch (error) {
                        throw new Error('Unexpected response received.');
                    }

                    if (!response.ok || result.status !== 'success') {
                        throw new Error(result.message || 'Unable to add the product.');
                    }

                    if (result.new_vendor_product) {
                        state.vendorProducts.push(result.new_vendor_product);
                        populateVendorProductSelect();
                    }

                    const pi = state.proformas.find((item) => item.token === activePiToken);
                    if (pi && result.product) {
                        pi.products = Array.isArray(pi.products) ? pi.products : [];
                        pi.products.push(result.product);

                        const tbody = document.querySelector(`[data-products-for="${escapeSelector(activePiToken)}"]`);
                        const emptyState = document.querySelector(`[data-empty-state-for="${escapeSelector(activePiToken)}"]`);

                        if (tbody) {
                            tbody.append(renderProductRow(result.product));
                        }

                        if (emptyState) {
                            emptyState.classList.add('d-none');
                        }
                    }

                    if (productModalInstance) {
                        productModalInstance.hide();
                    }

                    showAlert(piAlert, result.message || 'Product added.', 'success');
                } catch (error) {
                    showAlert(productAlertBox, error.message, 'danger');
                } finally {
                    submitButton.disabled = false;
                }
            });
        }
    };

    populateVendorProductSelect();
    refreshPiList();
    attachEventListeners();
});
