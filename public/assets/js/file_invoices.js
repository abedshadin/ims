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
    state.file = state.file && typeof state.file === 'object' ? state.file : null;

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
    const fileMetaCreated = document.getElementById('fileMetaCreated');
    const fileMetaUpdated = document.getElementById('fileMetaUpdated');
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

    const parseNumber = (value) => {
        if (typeof value === 'number') {
            return Number.isFinite(value) ? value : 0;
        }

        if (value === null || value === undefined) {
            return 0;
        }

        const sanitised = String(value).replace(/[^0-9+\-.,]/g, '').replace(/,/g, '');
        const parsed = Number.parseFloat(sanitised);

        return Number.isNaN(parsed) ? 0 : parsed;
    };

    const toCurrency = (value) => {
        return parseNumber(value).toFixed(2);
    };

    const formatQuantity = (value) => {
        const number = parseNumber(value);

        if (!Number.isFinite(number) || number === 0) {
            return '0';
        }

        const fixed = number.toFixed(3);
        return fixed.replace(/\.0+$/, '').replace(/\.([0-9]*[1-9])0+$/, '.$1');
    };

    const formatWeight = (value) => {
        const number = parseNumber(value);

        if (!Number.isFinite(number) || number === 0) {
            return '0';
        }

        return number.toFixed(3).replace(/\.0+$/, '').replace(/\.([0-9]*[1-9])0+$/, '.$1');
    };

    const updateFileMeta = (meta) => {
        if (!meta) {
            return;
        }

        state.file = {
            ...(state.file || {}),
            ...meta,
        };

        if (fileMetaCreated) {
            const createdAt = state.file.created_at_human || '';
            const createdBy = state.file.created_by_name || '';

            if (createdAt) {
                let createdText = `Created ${createdAt}`;

                if (createdBy) {
                    createdText += ` by ${createdBy}`;
                }

                fileMetaCreated.textContent = createdText;
            } else {
                fileMetaCreated.textContent = 'Created';
            }
        }

        if (fileMetaUpdated) {
            const updatedAt = state.file.updated_at_human || '';
            const updatedBy = state.file.updated_by_name || '';

            if (updatedAt) {
                let updatedText = `Last updated ${updatedAt}`;

                if (updatedBy) {
                    updatedText += ` by ${updatedBy}`;
                }

                fileMetaUpdated.textContent = updatedText;
            } else {
                fileMetaUpdated.textContent = 'Not updated yet';
            }
        }
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

    const renderProductRow = (line, metrics) => {
        const product = line.product;
        const quantityDisplay = formatQuantity(line.quantity);
        const fobDisplay = toCurrency(line.fobTotal);
        const fobPerUnitDisplay = toCurrency(line.fobPerUnit || (line.quantity > 0 ? line.fobTotal / line.quantity : 0));
        const freightPerUnitDisplay = toCurrency(line.freightPerUnit || 0);
        const freightShareDisplay = toCurrency(line.freightShare || 0);
        const cnfPerUnitDisplay = toCurrency(line.cnfPerUnit || 0);
        const cnfTotalDisplay = toCurrency(line.cnfTotal || 0);
        const totalWeightDisplay = formatWeight(line.lineWeight);
        const row = document.createElement('tr');
        const productToken = product.token || '';
        const canDelete = productToken !== '';
        row.dataset.productToken = productToken;
        row.dataset.piToken = metrics.piToken || '';
        row.innerHTML = `
            <td>
                <div class="fw-semibold">${escapeHtml(product.product_name || '')}</div>
                <div class="text-muted small">${escapeHtml(product.brand || '')}</div>
                ${canDelete
                    ? `<div class="mt-2"><button class="btn btn-danger btn-sm" type="button" data-action="delete-product" data-pi-token="${escapeHtml(metrics.piToken || '')}" data-product-token="${escapeHtml(productToken)}">Remove Product</button></div>`
                    : '<div class="mt-2 text-muted small">Not removable</div>'}
            </td>
            <td>
                <div>${escapeHtml(product.product_category || '')}</div>
                <div class="text-muted small">COO: ${escapeHtml(product.country_of_origin || '')}</div>
            </td>
            <td>
                <div>${escapeHtml(product.product_size || '')}</div>
                <div class="text-muted small">Unit: ${escapeHtml(product.unit || '')}</div>
                <div class="text-muted small">Unit Wt: ${escapeHtml(product.item_weight || '')}</div>
                <div class="text-muted small">Total Wt: ${escapeHtml(totalWeightDisplay)}</div>
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
            <td class="text-end">
                <div class="fw-semibold">${escapeHtml(quantityDisplay)}</div>
                <div class="text-muted small">FOB: $${escapeHtml(fobDisplay)}</div>
            </td>
            <td class="text-end">
                <div class="fw-semibold">C&amp;F Total $${escapeHtml(cnfTotalDisplay)}</div>
                <div class="text-muted small">Per Unit $${escapeHtml(cnfPerUnitDisplay)} (FOB $${escapeHtml(fobPerUnitDisplay)} + Freight $${escapeHtml(freightPerUnitDisplay)})</div>
                <div class="text-muted small">Freight Share $${escapeHtml(freightShareDisplay)}</div>
            </td>
        `;
        return row;
    };

    const calculateProformaMetrics = (proforma) => {
        const products = Array.isArray(proforma.products) ? proforma.products : [];
        const lines = [];
        let totalWeight = 0;
        let totalFob = 0;
        let totalQuantity = 0;
        let totalCnf = 0;

        products.forEach((product) => {
            const quantity = parseNumber(product.quantity);
            const fobTotal = parseNumber(product.fob_total);
            const weightPerUnit = parseNumber(product.item_weight);
            const lineWeight = weightPerUnit * quantity;

            totalWeight += lineWeight;
            totalFob += fobTotal;
            totalQuantity += quantity;

            lines.push({
                product,
                quantity,
                fobTotal,
                weightPerUnit,
                lineWeight,
            });
        });

        const totalFreight = parseNumber(proforma.freight_amount);
        const freightPerWeight = totalWeight > 0 ? totalFreight / totalWeight : 0;

        lines.forEach((line) => {
            const fobPerUnit = line.quantity > 0 ? line.fobTotal / line.quantity : 0;
            const freightPerUnit = line.weightPerUnit * freightPerWeight;
            const freightShare = freightPerUnit * line.quantity;
            const cnfPerUnit = freightPerUnit + fobPerUnit;
            const cnfTotal = cnfPerUnit * line.quantity;

            line.fobPerUnit = fobPerUnit;
            line.freightPerUnit = freightPerUnit;
            line.freightShare = freightShare;
            line.cnfPerUnit = cnfPerUnit;
            line.cnfTotal = cnfTotal;

            totalCnf += cnfTotal;
        });

        return {
            lines,
            totalWeight,
            totalFob,
            totalQuantity,
            totalFreight,
            freightPerWeight,
            totalCnf,
            piToken: proforma.token || '',
        };
    };

    const renderProformaCard = (proforma) => {
        const metrics = calculateProformaMetrics(proforma);
        const card = document.createElement('div');
        card.className = 'card shadow-sm border-0 mb-4';
        card.dataset.piToken = proforma.token || '';

        const createdAt = proforma.created_at_human || '';

        card.innerHTML = `
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3">
                    <div>
                        <h3 class="h5 mb-1">Proforma Invoice ${escapeHtml(proforma.invoice_number || '')}</h3>
                        <p class="text-muted small mb-2">Created ${escapeHtml(createdAt)}</p>
                        <div class="input-group input-group-sm" style="max-width: 22rem;">
                            <span class="input-group-text">$</span>
                            <input class="form-control" type="number" step="0.01" value="${escapeHtml(toCurrency(proforma.freight_amount || metrics.totalFreight || 0))}" data-freight-input>
                            <button class="btn btn-outline-primary" type="button" data-action="save-freight" data-pi-token="${escapeHtml(proforma.token || '')}">Save Freight</button>
                        </div>
                        <div class="text-muted small mt-1">Freight is distributed by total weight for C&amp;F.</div>
                    </div>
                    <div class="d-flex gap-2 align-items-start">
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
                                <th scope="col" class="text-end">Quantity &amp; FOB</th>
                                <th scope="col" class="text-end">C&amp;F Summary</th>
                            </tr>
                        </thead>
                        <tbody data-products-for="${escapeHtml(proforma.token || '')}"></tbody>
                    </table>
                </div>
                <p class="text-muted small mt-3 ${metrics.lines.length ? 'd-none' : ''}" data-empty-state-for="${escapeHtml(proforma.token || '')}">
                    No products have been added to this proforma invoice yet.
                </p>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-5 g-3 mt-3">
                    <div class="col">
                        <div class="text-muted text-uppercase small">Freight</div>
                        <div class="fw-semibold">$${toCurrency(metrics.totalFreight)}</div>
                    </div>
                    <div class="col">
                        <div class="text-muted text-uppercase small">Total Weight</div>
                        <div class="fw-semibold">${formatWeight(metrics.totalWeight)}</div>
                    </div>
                    <div class="col">
                        <div class="text-muted text-uppercase small">Freight / Weight</div>
                        <div class="fw-semibold">$${toCurrency(metrics.freightPerWeight)}</div>
                    </div>
                    <div class="col">
                        <div class="text-muted text-uppercase small">Total FOB</div>
                        <div class="fw-semibold">$${toCurrency(metrics.totalFob)}</div>
                    </div>
                    <div class="col">
                        <div class="text-muted text-uppercase small">Total C&amp;F</div>
                        <div class="fw-semibold">$${toCurrency(metrics.totalCnf)}</div>
                    </div>
                </div>
            </div>
        `;

        const tbody = card.querySelector(`[data-products-for="${escapeSelector(proforma.token || '')}"]`);

        if (tbody && metrics.lines.length) {
            metrics.lines.forEach((line) => {
                tbody.append(renderProductRow(line, metrics));
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

                    if (result.file_meta) {
                        updateFileMeta(result.file_meta);
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
            piList.addEventListener('click', async (event) => {
                const actionButton = event.target.closest('[data-action]');

                if (!actionButton) {
                    return;
                }

                const action = actionButton.getAttribute('data-action');
                const piToken = actionButton.getAttribute('data-pi-token') || '';

                if (!piToken) {
                    return;
                }

                if (action === 'add-product') {
                    openProductModal(piToken);
                    return;
                }

                if (action === 'save-freight') {
                    const card = actionButton.closest('[data-pi-token]');
                    const input = card ? card.querySelector('[data-freight-input]') : null;

                    if (!input) {
                        return;
                    }

                    const originalText = actionButton.innerHTML;
                    actionButton.disabled = true;
                    actionButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

                    try {
                        resetAlert(piAlert);
                        const formData = new FormData();
                        formData.set('pi_token', piToken);
                        formData.set('freight_amount', input.value || '');

                        const response = await fetch('proforma_freight_update.php', {
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
                            throw new Error(result.message || 'Unable to update freight.');
                        }

                        const pi = state.proformas.find((item) => item.token === piToken);
                        if (pi) {
                            pi.freight_amount = result.freight_amount;
                            pi.freight_amount_formatted = result.freight_amount;
                        }

                        if (result.file_meta) {
                            updateFileMeta(result.file_meta);
                        }

                        refreshPiList();
                        showAlert(piAlert, result.message || 'Freight updated.', 'success');
                    } catch (error) {
                        showAlert(piAlert, error.message, 'danger');
                    } finally {
                        actionButton.disabled = false;
                        actionButton.innerHTML = originalText;
                    }

                    return;
                }

                if (action === 'delete-product') {
                    const productToken = actionButton.getAttribute('data-product-token') || '';

                    if (!productToken) {
                        return;
                    }

                    if (!window.confirm('Remove this product from the proforma invoice?')) {
                        return;
                    }

                    const originalText = actionButton.innerHTML;
                    actionButton.disabled = true;
                    actionButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

                    try {
                        resetAlert(piAlert);
                        const formData = new FormData();
                        formData.set('pi_token', piToken);
                        formData.set('product_token', productToken);

                        const response = await fetch('proforma_products_delete.php', {
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
                            throw new Error(result.message || 'Unable to remove the product.');
                        }

                        const pi = state.proformas.find((item) => item.token === piToken);
                        if (pi) {
                            pi.products = (pi.products || []).filter((product) => product.token !== productToken);
                        }

                        if (result.file_meta) {
                            updateFileMeta(result.file_meta);
                        }

                        refreshPiList();
                        showAlert(piAlert, result.message || 'Product removed.', 'success');
                    } catch (error) {
                        showAlert(piAlert, error.message, 'danger');
                    } finally {
                        actionButton.disabled = false;
                        actionButton.innerHTML = originalText;
                    }
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
                    }

                    refreshPiList();

                    if (result.file_meta) {
                        updateFileMeta(result.file_meta);
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

    if (state.file) {
        updateFileMeta(state.file);
    }

    populateVendorProductSelect();
    refreshPiList();
    attachEventListeners();
});
