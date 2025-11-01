import {
    parseNumber,
    toCurrency,
    getCurrencySymbol,
    formatQuantity,
    formatWeight,
    formatFreight,
    formatPercent,
    formatToleranceValue,
    formatTolerance,
    formatDate,
    currencyToWords,
    escapeHtml,
    formatMultiline,
} from './formatting.js';
import {
    escapeSelector,
    normaliseProforma,
    normaliseReference,
    normaliseCommercialInvoice,
    buildInitialState,
    calculateProformaMetrics,
    buildBankReference,
} from './normalisation.js';
import {
    openPrintPreview,
    renderCnfPreview,
    renderBankForwardingPreview,
    renderTocPreview,
    buildLetterStyles,
} from './printing.js';

/* global bootstrap */

document.addEventListener('DOMContentLoaded', () => {
    const dataElement = document.getElementById('fileInvoicesData');

    if (!dataElement) {
        return;
    }

    const state = buildInitialState(dataElement);

    const piList = document.getElementById('piList');
    const noPiMessage = document.getElementById('noPiMessage');
    const createPiForm = document.getElementById('createPiForm');
    const piAlert = document.getElementById('piAlert');
    const ciList = document.getElementById('ciList');
    const noCiMessage = document.getElementById('noCiMessage');
    const createCiForm = document.getElementById('createCiForm');
    const ciAlert = document.getElementById('ciAlert');
    const productModalElement = document.getElementById('productModal');
    const productForm = document.getElementById('productForm');
    const productFormSubmit = document.getElementById('productFormSubmit');
    const vendorProductSelect = document.getElementById('vendor_product_id');
    const productModeInputs = document.querySelectorAll('input[name="product_mode"]');
    const existingProductFields = document.getElementById('existingProductFields');
    const newProductFields = document.getElementById('newProductFields');
    const productAlertBox = document.getElementById('productFormAlert');
    const piProductSelect = document.getElementById('pi_product_id');
    const piProductRemoveButton = document.getElementById('piProductRemoveButton');
    const piProductRemoveAlert = document.getElementById('piProductRemoveAlert');
    const productPreview = document.getElementById('vendorProductPreview');
    const previewFields = productPreview ? productPreview.querySelectorAll('[data-preview]') : [];
    const fileMetaCreated = document.getElementById('fileMetaCreated');
    const fileMetaUpdated = document.getElementById('fileMetaUpdated');
    const lcForm = document.getElementById('lcForm');
    const lcAlert = document.getElementById('lcAlert');
    const lcSummary = document.getElementById('lcSummary');
    const lcEmptyMessage = lcSummary ? lcSummary.querySelector('[data-lc-empty-message]') : null;
    const lcCurrencySelect = lcForm ? lcForm.elements.currency : null;
    const lcCurrencyPrefix = lcForm ? lcForm.querySelector('[data-lc-currency-prefix]') : null;
    const insuranceForm = document.getElementById('insuranceForm');
    const insuranceAlert = document.getElementById('insuranceAlert');
    const insuranceSummary = document.getElementById('insuranceSummary');
    const insuranceEmptyMessage = insuranceSummary ? insuranceSummary.querySelector('[data-insurance-empty-message]') : null;
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

    const clearFormValidation = (form) => {
        if (!form) {
            return;
        }

        Array.from(form.elements).forEach((element) => {
            if (element instanceof HTMLInputElement || element instanceof HTMLSelectElement || element instanceof HTMLTextAreaElement) {
                element.classList.remove('is-invalid');
            }
        });
    };

    const applyFormErrors = (form, errors) => {
        if (!form || !errors) {
            return;
        }

        Object.entries(errors).forEach(([name]) => {
            const field = form.elements[name];
            if (field && field.classList) {
                field.classList.add('is-invalid');
            }
        });
    };

    const applyCurrencyPrefix = (currency) => {
        if (!lcCurrencyPrefix) {
            return;
        }

        lcCurrencyPrefix.textContent = getCurrencySymbol(currency);
    };

    const getBankProfile = () => {
        if (state.bank && typeof state.bank === 'object') {
            return state.bank;
        }

        if (state.file && typeof state.file.bank_profile === 'object') {
            return state.file.bank_profile;
        }

        return null;
    };

    const updateFileMeta = (meta) => {
        if (!meta) {
            return;
        }

        state.file = {
            ...(state.file || {}),
            ...meta,
        };

        if (meta.bank_profile && typeof meta.bank_profile === 'object') {
            state.bank = meta.bank_profile;
        }

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

    const getLcFieldElement = (name) => {
        return lcSummary ? lcSummary.querySelector(`[data-lc-field="${name}"]`) : null;
    };

    const syncLcForm = (lc) => {
        if (!lcForm) {
            return;
        }

        const mappings = {
            lc_number: 'lc_number',
            lc_type: 'lc_type',
            lc_date: 'lc_date',
            currency: 'currency',
            subject_line: 'subject_line',
            lc_amount: 'lc_amount',
            latest_shipment_date: 'latest_shipment_date',
            expiry_date: 'expiry_date',
        };

        Object.entries(mappings).forEach(([key, fieldName]) => {
            const field = lcForm.elements[fieldName];
            if (!field) {
                return;
            }

            const value = lc && typeof lc === 'object' ? (lc[key] || '') : '';
            if (value === '' && typeof field.dataset.defaultValue !== 'undefined') {
                field.value = field.dataset.defaultValue;
            } else {
                field.value = value;
            }
        });

        if (lcCurrencySelect) {
            const currentCurrency = lcCurrencySelect.value || lcCurrencySelect.dataset.defaultValue || 'USD';
            applyCurrencyPrefix(currentCurrency);
        } else {
            applyCurrencyPrefix('USD');
        }
    };

    const updateLcSummary = (lc) => {
        state.lc = lc && typeof lc === 'object' ? lc : null;

        const hasDetails = Boolean(
            state.lc &&
            (state.lc.lc_number || state.lc.lc_type || state.lc.lc_date)
        );

        const setFieldText = (name, value, fallback = '—') => {
            const element = getLcFieldElement(name);
            if (!element) {
                return;
            }

            const display = value && value !== '' ? value : fallback;
            element.textContent = display;
        };

        if (state.lc) {
            setFieldText('lc_number', state.lc.lc_number, '—');
            setFieldText('lc_type', state.lc.lc_type, '—');
            setFieldText('subject_line', state.lc.subject_line, '—');
            setFieldText('lc_date_human', state.lc.lc_date_human || state.lc.lc_date, '—');
            setFieldText('currency', state.lc.currency, '—');
            setFieldText('latest_shipment_date_human', state.lc.latest_shipment_date_human || state.lc.latest_shipment_date, '—');
            setFieldText('expiry_date_human', state.lc.expiry_date_human || state.lc.expiry_date, '—');

            const amountElement = getLcFieldElement('lc_amount');
            if (amountElement) {
                const amountValue = state.lc.lc_amount && state.lc.lc_amount !== ''
                    ? toCurrency(state.lc.lc_amount)
                    : toCurrency(state.lc.lc_amount_formatted || '0');
                const currencySymbol = getCurrencySymbol(state.lc.currency);
                amountElement.textContent = `${currencySymbol}${amountValue}`;
            }
        } else {
            setFieldText('lc_number', '', '—');
            setFieldText('lc_type', '', '—');
            setFieldText('subject_line', '', '—');
            setFieldText('lc_date_human', '', '—');
            setFieldText('currency', '', '—');
            setFieldText('latest_shipment_date_human', '', '—');
            setFieldText('expiry_date_human', '', '—');

            const amountElement = getLcFieldElement('lc_amount');
            if (amountElement) {
                amountElement.textContent = `${getCurrencySymbol('USD')}0.00`;
            }
        }

        if (lcEmptyMessage) {
            if (hasDetails) {
                lcEmptyMessage.classList.add('d-none');
            } else {
                lcEmptyMessage.classList.remove('d-none');
            }
        }
    };

    const getInsuranceFieldElement = (name) => {
        return insuranceSummary ? insuranceSummary.querySelector(`[data-insurance-field="${name}"]`) : null;
    };

    const setInsuranceFieldText = (name, value, fallback = '—') => {
        const element = getInsuranceFieldElement(name);
        if (!element) {
            return;
        }

        const display = value && value !== '' ? value : fallback;
        element.textContent = display;
    };

    const formatExchangeRate = (value) => {
        return parseNumber(value).toFixed(4);
    };

    const syncInsuranceForm = (insurance) => {
        if (!insuranceForm) {
            return;
        }

        const mappings = {
            money_receipt_no: 'money_receipt_no',
            money_receipt_date: 'money_receipt_date',
            exchange_rate: 'exchange_rate',
            insurance_value: 'insurance_value',
        };

        Object.entries(mappings).forEach(([key, fieldName]) => {
            const field = insuranceForm.elements[fieldName];
            if (!field) {
                return;
            }

            const value = insurance && typeof insurance === 'object' ? (insurance[key] || '') : '';
            field.value = value;
        });
    };

    const updateInsuranceSummary = (insurance) => {
        state.insurance = insurance && typeof insurance === 'object' ? insurance : null;

        const hasInsuranceDetails = Boolean(
            state.insurance &&
            (state.insurance.money_receipt_no || state.insurance.money_receipt_date || state.insurance.insurance_value)
        );

        if (state.insurance) {
            setInsuranceFieldText('money_receipt_no', state.insurance.money_receipt_no, '—');
            setInsuranceFieldText(
                'money_receipt_date_human',
                state.insurance.money_receipt_date_human || state.insurance.money_receipt_date,
                '—',
            );

            const exchangeRateElement = getInsuranceFieldElement('exchange_rate');
            if (exchangeRateElement) {
                const exchangeValue = state.insurance.exchange_rate && state.insurance.exchange_rate !== ''
                    ? formatExchangeRate(state.insurance.exchange_rate)
                    : formatExchangeRate(state.insurance.exchange_rate_formatted || '0');
                exchangeRateElement.textContent = exchangeValue;
            }

            const insuranceValueElement = getInsuranceFieldElement('insurance_value');
            if (insuranceValueElement) {
                const valueAmount = state.insurance.insurance_value && state.insurance.insurance_value !== ''
                    ? toCurrency(state.insurance.insurance_value)
                    : toCurrency(state.insurance.insurance_value_formatted || '0');
                insuranceValueElement.textContent = `$${valueAmount}`;
            }
        } else {
            setInsuranceFieldText('money_receipt_no', '', '—');
            setInsuranceFieldText('money_receipt_date_human', '', '—');

            const exchangeRateElement = getInsuranceFieldElement('exchange_rate');
            if (exchangeRateElement) {
                exchangeRateElement.textContent = formatExchangeRate(0);
            }

            const insuranceValueElement = getInsuranceFieldElement('insurance_value');
            if (insuranceValueElement) {
                insuranceValueElement.textContent = '$0.00';
            }
        }

        if (insuranceEmptyMessage) {
            if (hasInsuranceDetails) {
                insuranceEmptyMessage.classList.add('d-none');
            } else {
                insuranceEmptyMessage.classList.remove('d-none');
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
        const cnfPerUnitDisplay = toCurrency(line.cnfPerUnit || 0);
        const cnfTotalDisplay = toCurrency(line.cnfTotal || 0);
        const productWeightDisplay = formatWeight(line.productWeight);
        const freightPerWeightDisplay = formatFreight(line.freightPerWeight || 0);
        const fobPerWeightDisplay = formatFreight(line.fobPerWeight || 0);
        const cnfPerWeightDisplay = formatFreight(line.cnfPerWeight || 0);
        const cnfCalcExpression = line.cnfCalcExpression || 'Calculation unavailable (missing weight)';
        const cnfCalcComponents = line.cnfCalcComponents || 'Freight or weight data missing for this product';
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
                    ? `<div class="mt-2"><button class="btn btn-outline-danger btn-sm" type="button" data-action="delete-product" data-pi-token="${escapeHtml(metrics.piToken || '')}" data-product-token="${escapeHtml(productToken)}">Remove Product</button></div>`
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
                <div class="text-muted small">Total Wt: ${escapeHtml(productWeightDisplay)}</div>
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
            

                <div class="text-muted small">C&amp;F Per Unit $${escapeHtml(cnfPerWeightDisplay)} </div>
            
            </td>
        `;
        return row;
    };

    const renderCommercialCard = (invoice) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'col-12';

        const card = document.createElement('div');
        card.className = 'workspace-section-card card shadow-sm border-0';
        card.dataset.ciToken = invoice.token || '';

        const invoiceNumber = invoice.invoice_number || '';
        const invoiceDateDisplay = invoice.invoice_date_formatted
            || (invoice.invoice_date ? formatDate(invoice.invoice_date, { day: '2-digit', month: 'short', year: 'numeric' }) : '');
        const totalValueDisplay = toCurrency(invoice.total_value || invoice.total_value_formatted || 0);
        const createdAt = invoice.created_at_human || '';
        const proformaNumber = invoice.proforma && invoice.proforma.invoice_number
            ? `PI ${invoice.proforma.invoice_number}`
            : '';
        const productRows = (invoice.products || [])
            .filter((product) => product && product.token)
            .map((product) => {
                const productName = product.product_name || 'Unnamed Product';
                const brand = product.brand ? `<div class="text-muted small">${escapeHtml(product.brand)}</div>` : '';
                const category = product.product_category ? `<div class="text-muted small">${escapeHtml(product.product_category)}</div>` : '';
                const country = product.country_of_origin ? `<div class="text-muted small">Origin: ${escapeHtml(product.country_of_origin)}</div>` : '';
                const size = product.product_size ? `<div class="text-muted small">Size: ${escapeHtml(product.product_size)}</div>` : '';
                const unit = product.unit ? `<div class="text-muted small">Unit: ${escapeHtml(product.unit)}</div>` : '';
                const hsCode = product.hs_code ? `<div class="text-muted small">HS: ${escapeHtml(product.hs_code)}</div>` : '';

                return `
                    <tr data-product-token="${escapeHtml(product.token)}">
                        <td>
                            <div class="fw-semibold">${escapeHtml(productName)}</div>
                            ${brand}
                        </td>
                        <td>
                            ${category}
                            ${country}
                            ${size}
                            ${unit}
                            ${hsCode}
                        </td>
                        <td class="text-end">
                            <input class="form-control form-control-sm text-end" type="number" step="0.001" min="0" name="products[${escapeHtml(product.token)}][final_quantity]" value="${escapeHtml(product.final_quantity || '0')}" required>
                        </td>
                        <td class="text-end">
                            <input class="form-control form-control-sm text-end" type="number" step="0.01" min="0" name="products[${escapeHtml(product.token)}][final_unit_price]" value="${escapeHtml(product.final_unit_price || '0.00')}" required>
                        </td>
                        <td class="text-end">
                            <input class="form-control form-control-sm text-end" type="number" step="0.01" min="0" name="products[${escapeHtml(product.token)}][total_item_price]" value="${escapeHtml(product.total_item_price || '0.00')}" required>
                        </td>
                        <td class="text-end">
                            <input class="form-control form-control-sm text-end" type="number" step="0.0001" min="0" name="products[${escapeHtml(product.token)}][unit_freight]" value="${escapeHtml(product.unit_freight || '0.0000')}" required>
                        </td>
                        <td class="text-end">
                            <input class="form-control form-control-sm text-end" type="number" step="0.01" min="0" name="products[${escapeHtml(product.token)}][total_freight]" value="${escapeHtml(product.total_freight || '0.00')}" required>
                        </td>
                        <td class="text-end">
                            <input class="form-control form-control-sm text-end" type="number" step="0.001" min="0" name="products[${escapeHtml(product.token)}][item_weight]" value="${escapeHtml(product.item_weight || '0')}" required>
                        </td>
                        <td class="text-end">
                            <input class="form-control form-control-sm text-end" type="number" step="0.001" min="0" name="products[${escapeHtml(product.token)}][total_weight]" value="${escapeHtml(product.total_weight || '0')}" required>
                        </td>
                        <td class="text-end">
                            <input class="form-control form-control-sm text-end" type="number" step="0.01" min="0" name="products[${escapeHtml(product.token)}][total_cnf_value]" value="${escapeHtml(product.total_cnf_value || '0.00')}" required>
                        </td>
                        <td class="text-end">
                            <input class="form-control form-control-sm text-end" type="number" step="0.01" min="0" name="products[${escapeHtml(product.token)}][invoice_total]" value="${escapeHtml(product.invoice_total || '0.00')}" required>
                        </td>
                    </tr>
                `;
            })
            .join('');

        const tableBody = productRows || '<tr><td class="text-center text-muted py-4" colspan="11">No products available for this commercial invoice.</td></tr>';

        card.innerHTML = `
            <div class="card-header bg-white border-0 pb-0">
                <div class="d-flex flex-column flex-xl-row justify-content-between align-items-start align-items-xl-center gap-4">
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <span class="badge rounded-pill text-bg-success-subtle text-success fw-semibold">CI</span>
                            <h2 class="h5 mb-0">Commercial ${escapeHtml(invoiceNumber)}</h2>
                            <span class="badge text-bg-light text-primary-emphasis">Total $${escapeHtml(totalValueDisplay)}</span>
                            ${proformaNumber ? `<span class="badge text-bg-secondary-subtle text-secondary">Based on ${escapeHtml(proformaNumber)}</span>` : ''}
                            ${invoiceDateDisplay ? `<span class="badge text-bg-info-subtle text-info">Dated ${escapeHtml(invoiceDateDisplay)}</span>` : ''}
                        </div>
                        <p class="text-muted small mb-0">Created ${escapeHtml(createdAt)}</p>
                    </div>
                </div>
            </div>
            <div class="card-body pt-4" data-ci-container>
                <div class="alert d-none" data-ci-message role="alert"></div>
                <form class="ci-form" data-ci-form data-ci-token="${escapeHtml(invoice.token || '')}" novalidate>
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-4">
                            <label class="form-label text-uppercase small fw-semibold">Commercial Invoice Number</label>
                            <input class="form-control form-control-sm" type="text" name="invoice_number" value="${escapeHtml(invoiceNumber)}" required>
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label text-uppercase small fw-semibold">Invoice Date</label>
                            <input class="form-control form-control-sm" type="date" name="invoice_date" value="${escapeHtml(invoice.invoice_date || '')}" required>
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label text-uppercase small fw-semibold">Base Proforma</label>
                            <input class="form-control form-control-sm" type="text" value="${escapeHtml(proformaNumber || 'Not linked')}" readonly>
                        </div>
                        <div class="col-lg-2 d-flex align-items-end">
                            <button class="btn btn-outline-secondary w-100" type="submit" data-ci-submit>Save Changes</button>
                        </div>
                    </div>

                    <div class="workspace-stat-grid mt-3">
                        <div class="workspace-stat">
                            <span class="workspace-stat-label">Products</span>
                            <span class="workspace-stat-value">${invoice.products ? invoice.products.length : 0}</span>
                        </div>
                        <div class="workspace-stat">
                            <span class="workspace-stat-label">Invoice Date</span>
                            <span class="workspace-stat-value">${escapeHtml(invoiceDateDisplay || 'Not set')}</span>
                        </div>
                        <div class="workspace-stat">
                            <span class="workspace-stat-label">Total Invoice Value</span>
                            <span class="workspace-stat-value">$${escapeHtml(totalValueDisplay)}</span>
                        </div>
                    </div>

                    <div class="table-responsive mt-4">
                        <table class="table table-sm table-hover align-middle mb-0 workspace-product-table">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Product</th>
                                    <th scope="col">Category &amp; Details</th>
                                    <th scope="col" class="text-end">Final Qty</th>
                                    <th scope="col" class="text-end">Final Unit Price</th>
                                    <th scope="col" class="text-end">Total Item Price</th>
                                    <th scope="col" class="text-end">Unit Freight</th>
                                    <th scope="col" class="text-end">Total Freight</th>
                                    <th scope="col" class="text-end">Item Weight</th>
                                    <th scope="col" class="text-end">Total Weight</th>
                                    <th scope="col" class="text-end">Total C&amp;F</th>
                                    <th scope="col" class="text-end">Total Invoice Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${tableBody}
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        `;

        wrapper.append(card);

        return wrapper;
    };

    const refreshCiList = () => {
        if (!ciList) {
            return;
        }

        ciList.innerHTML = '';

        state.commercialInvoices.forEach((invoice) => {
            const cardWrapper = renderCommercialCard(invoice);
            ciList.append(cardWrapper);
        });

        if (noCiMessage) {
            if (state.commercialInvoices.length === 0) {
                noCiMessage.classList.remove('d-none');
            } else {
                noCiMessage.classList.add('d-none');
            }
        }
    };

    const renderProformaCard = (proforma) => {
        const metrics = calculateProformaMetrics(proforma);
        const wrapper = document.createElement('div');
        wrapper.className = 'col-12';

        const card = document.createElement('div');
        card.className = 'workspace-section-card card shadow-sm border-0';
        card.dataset.piToken = proforma.token || '';

        const createdAt = proforma.created_at_human || '';
        const piHeaderValue = proforma.pi_header || '';
        const reference = normaliseReference(proforma.reference);
        const referenceCode = reference.code || buildBankReference(proforma, state.file);
        const referenceDateFormatted = reference.date_formatted
            || (reference.date ? formatDate(reference.date, { day: '2-digit', month: 'short', year: 'numeric' }) : '');
        const freightValue = toCurrency(proforma.freight_amount || metrics.totalFreight || 0);
        const toleranceValue = formatToleranceValue(proforma.tolerance_percentage);
        const toleranceDisplay = formatTolerance(toleranceValue);
        const totalWeightDisplay = formatWeight(metrics.totalWeight);
        const freightPerWeightDisplay = formatFreight(metrics.freightPerWeight);

        card.innerHTML = `
            <div class="card-header bg-white border-0 pb-0">
                <div class="d-flex flex-column flex-xl-row justify-content-between align-items-start align-items-xl-center gap-4">
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <span class="badge rounded-pill text-bg-primary-subtle text-primary fw-semibold">PI</span>
                            <h2 class="h5 mb-0">Proforma ${escapeHtml(proforma.invoice_number || '')}</h2>
                            <span class="badge text-bg-light text-primary-emphasis">Freight $${escapeHtml(freightValue)}</span>
                            <span class="badge text-bg-secondary-subtle text-secondary">Tolerance ${escapeHtml(toleranceDisplay)}</span>
                            ${piHeaderValue ? `<span class="badge text-bg-info-subtle text-info">Header: ${escapeHtml(piHeaderValue)}</span>` : ''}
                        </div>
                        <p class="text-muted small mb-0">Created ${escapeHtml(createdAt)}</p>
                    </div>
                    <div class="workspace-pi-actions d-flex flex-column flex-sm-row flex-wrap gap-2 w-100 w-xl-auto justify-content-xl-end">
                        <button class="btn btn-outline-primary flex-fill" type="button" data-action="print-cnf" data-pi-token="${escapeHtml(proforma.token || '')}">C&amp;F Calc Print &amp; Preview</button>
                        <button class="btn btn-outline-primary flex-fill" type="button" data-action="print-bank-forwarding" data-pi-token="${escapeHtml(proforma.token || '')}">Bank Forwarding Print &amp; Preview</button>
                        <button class="btn btn-outline-primary flex-fill" type="button" data-action="print-toc" data-pi-token="${escapeHtml(proforma.token || '')}">ToC Print &amp; Preview</button>
                        <button class="btn btn-primary flex-fill" type="button" data-action="add-product" data-pi-token="${escapeHtml(proforma.token || '')}">Add Product</button>
                    </div>
                </div>
            </div>
            <div class="card-body pt-4">
                <div class="workspace-inline-control border rounded-3 p-3 d-flex flex-column flex-md-row align-items-md-center gap-3">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input class="form-control" type="number" step="0.01" value="${escapeHtml(freightValue)}" data-freight-input>
                            </div>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">%</span>
                                <input class="form-control" type="number" step="0.01" min="0" max="100" value="${escapeHtml(toleranceValue)}" data-tolerance-input>
                            </div>
                        <button class="btn btn-outline-primary btn-sm" type="button" data-action="save-freight" data-pi-token="${escapeHtml(proforma.token || '')}">Save Freight</button>
                    </div>
                    <small class="text-muted">Freight is automatically distributed by weight when calculating C&amp;F totals.</small>
                </div>

                <div class="card bg-light border-0 mt-3">
                    <div class="card-body py-3">
                        <div class="row g-3 align-items-end">
                            <div class="col-lg-4">
                                <label class="form-label text-uppercase small fw-semibold" for="pi_header_${escapeHtml(proforma.token || '')}">PI Header</label>
                                <input class="form-control form-control-sm" type="text" id="pi_header_${escapeHtml(proforma.token || '')}" value="${escapeHtml(piHeaderValue)}" data-pi-header-input>
                            </div>
                            <div class="col-lg-3">
                                <label class="form-label text-uppercase small fw-semibold">Bank Reference</label>
                                <input class="form-control form-control-sm" type="text" value="${escapeHtml(referenceCode)}" data-bank-reference readonly>
                            </div>
                            <div class="col-lg-3">
                                <label class="form-label text-uppercase small fw-semibold" for="bank_ref_date_${escapeHtml(proforma.token || '')}">Bank Ref Date</label>
                                <input class="form-control form-control-sm" type="date" id="bank_ref_date_${escapeHtml(proforma.token || '')}" value="${escapeHtml(reference.date)}" data-bank-ref-date>
                                ${referenceDateFormatted ? `<div class="text-muted small mt-1">Saved as ${escapeHtml(referenceDateFormatted)}</div>` : ''}
                            </div>
                            <div class="col-lg-2 d-flex align-items-end">
                                <button class="btn btn-outline-secondary w-100" type="button" data-action="save-pi-details" data-pi-token="${escapeHtml(proforma.token || '')}">Save</button>
                            </div>
                        </div>
                        <div class="text-muted small mt-2">Bank letters append the PI header to “Opening L/C for Import”.</div>
                    </div>
                </div>

                <div class="workspace-stat-grid mt-3">
                    <div class="workspace-stat">
                        <span class="workspace-stat-label">Products</span>
                        <span class="workspace-stat-value">${metrics.lines.length}</span>
                    </div>
                    <div class="workspace-stat">
                        <span class="workspace-stat-label">Total Weight</span>
                        <span class="workspace-stat-value">${escapeHtml(totalWeightDisplay)}</span>
                    </div>
                    <div class="workspace-stat">
                        <span class="workspace-stat-label">Freight / Weight</span>
                        <span class="workspace-stat-value">$${escapeHtml(freightPerWeightDisplay)}</span>
                    </div>
                </div>

                <div class="table-responsive mt-4">
                    <table class="table table-sm table-hover align-middle mb-0 workspace-product-table">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Product</th>
                                <th scope="col">Category &amp; COO</th>
                                <th scope="col">Size &amp; Unit</th>
                                <th scope="col" class="text-end">Unit Rate</th>
                                <th scope="col" class="text-end">DEC &amp; HS</th>
                                <th scope="col" class="text-end">Assessment</th>
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
            </div>
        `;

        wrapper.append(card);

        const tbody = card.querySelector(`[data-products-for="${escapeSelector(proforma.token || '')}"]`);

        if (tbody) {
            if (metrics.lines.length) {
                metrics.lines.forEach((line) => {
                    tbody.append(renderProductRow(line, metrics));
                });
            } else {
                const emptyRow = document.createElement('tr');
                emptyRow.innerHTML = `
                    <td colspan="8" class="text-center text-muted py-5">
                        <div class="workspace-empty-state bg-transparent border-0 shadow-none p-0">
                            <div class="emoji">📦</div>
                            <p class="lead mb-1">No products yet</p>
                            <p class="text-muted mb-0">Use the “Add Product” button to attach vendor items or create new ones.</p>
                        </div>
                    </td>
                `;
                tbody.append(emptyRow);
            }
        }

        return wrapper;
    };
;;

    const deletePiProduct = async (piToken, productToken) => {
        const formData = new FormData();
        formData.set('pi_token', piToken);
        formData.set('product_token', productToken);

        const response = await fetch('proforma_products_delete.php', {
            method: 'POST',
            body: formData,
        });

        if (response.status === 401) {
            window.location.reload();
            throw new Error('Session expired. Reloading.');
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

        if (activePiToken === piToken) {
            populatePiProductRemovalOptions(piToken);
        }

        return result;
    };

    const refreshPiList = () => {
        if (!piList) {
            return;
        }

        piList.innerHTML = '';

        state.proformas.forEach((proforma) => {
            const cardWrapper = renderProformaCard(proforma);
            piList.append(cardWrapper);
        });

        if (noPiMessage) {
            if (state.proformas.length === 0) {
                noPiMessage.classList.remove('d-none');
            } else {
                noPiMessage.classList.add('d-none');
            }
        }
    };

    const populatePiProductRemovalOptions = (piToken) => {
        if (!piProductSelect) {
            return;
        }

        piProductSelect.innerHTML = '<option value="">Select a product to remove</option>';

        const pi = state.proformas.find((item) => item.token === piToken);
        const products = pi && Array.isArray(pi.products) ? pi.products : [];

        products
            .filter((product) => product && product.token)
            .forEach((product) => {
                const option = document.createElement('option');
                option.value = product.token;
                const name = product.product_name || 'Unnamed Product';
                const brand = product.brand ? ` · ${product.brand}` : '';
                option.textContent = `${name}${brand}`;
                piProductSelect.append(option);
            });

        const hasProducts = products.some((product) => product && product.token);

        piProductSelect.disabled = !hasProducts;
        
        if (piProductRemoveButton) {
            piProductRemoveButton.disabled = !hasProducts;
        }

        if (!hasProducts) {
            piProductSelect.value = '';
        }
    };

    const openProductModal = (piToken) => {
        if (!productForm || !productModalElement) {
            return;
        }

        activePiToken = piToken;
        productForm.reset();
        resetAlert(productAlertBox);
        resetAlert(piProductRemoveAlert);

        const piTokenInput = productForm.querySelector('#pi_token');
        if (piTokenInput) {
            piTokenInput.value = piToken;
        }

        populateVendorProductSelect();
        updateProductPreview();
        toggleProductMode();
        populatePiProductRemovalOptions(piToken);

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
        if (lcCurrencySelect) {
            lcCurrencySelect.addEventListener('change', () => {
                const currentCurrency = lcCurrencySelect.value || lcCurrencySelect.dataset.defaultValue || 'USD';
                applyCurrencyPrefix(currentCurrency);
            });
        }

        if (lcForm) {
            lcForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                resetAlert(lcAlert);
                clearFormValidation(lcForm);

                const submitButton = lcForm.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                }

                try {
                    const formData = new FormData(lcForm);
                    const response = await fetch('lc_store.php', {
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
                        applyFormErrors(lcForm, result.errors || {});
                        throw new Error(result.message || 'Unable to save the letter of credit details.');
                    }

                    if (result.lc) {
                        updateLcSummary(result.lc);
                        syncLcForm(result.lc);
                    }

                    if (result.file_meta) {
                        updateFileMeta(result.file_meta);
                    }

                    showAlert(lcAlert, result.message || 'Letter of credit details saved successfully.', 'success');
                } catch (error) {
                    showAlert(lcAlert, error.message, 'danger');
                } finally {
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                }
            });
        }

        if (insuranceForm) {
            insuranceForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                resetAlert(insuranceAlert);
                clearFormValidation(insuranceForm);

                const submitButton = insuranceForm.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                }

                try {
                    const formData = new FormData(insuranceForm);
                    const response = await fetch('insurance_store.php', {
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
                        applyFormErrors(insuranceForm, result.errors || {});
                        throw new Error(result.message || 'Unable to save the insurance details.');
                    }

                    if (result.insurance) {
                        updateInsuranceSummary(result.insurance);
                        syncInsuranceForm(result.insurance);
                    }

                    if (result.file_meta) {
                        updateFileMeta(result.file_meta);
                    }

                    showAlert(insuranceAlert, result.message || 'Insurance details saved successfully.', 'success');
                } catch (error) {
                    showAlert(insuranceAlert, error.message, 'danger');
                } finally {
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                }
            });
        }

        if (createCiForm) {
            createCiForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                resetAlert(ciAlert);

                const submitButton = createCiForm.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                }

                try {
                    const formData = new FormData(createCiForm);
                    const response = await fetch('commercial_store.php', {
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
                        throw new Error(result.message || 'Unable to create the commercial invoice.');
                    }

                    if (result.invoice) {
                        const normalised = normaliseCommercialInvoice(result.invoice);
                        if (normalised) {
                            state.commercialInvoices.unshift(normalised);
                        }
                        refreshCiList();
                        createCiForm.reset();
                    }

                    if (result.file_meta) {
                        updateFileMeta(result.file_meta);
                    }

                    showAlert(ciAlert, result.message || 'Commercial invoice created.', 'success');
                } catch (error) {
                    showAlert(ciAlert, error.message, 'danger');
                } finally {
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                }
            });
        }

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
                        const normalised = normaliseProforma(result.proforma);
                        if (normalised) {
                            state.proformas.unshift(normalised);
                        }
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

        if (ciList) {
            ciList.addEventListener('submit', async (event) => {
                const form = event.target.closest('form[data-ci-form]');

                if (!form) {
                    return;
                }

                event.preventDefault();

                const ciToken = form.getAttribute('data-ci-token') || '';

                if (!ciToken) {
                    showAlert(ciAlert, 'Unable to determine the selected commercial invoice.', 'danger');
                    return;
                }

                const messageBox = form.querySelector('[data-ci-message]');
                resetAlert(messageBox);

                const submitButton = form.querySelector('[data-ci-submit]');
                if (submitButton) {
                    submitButton.disabled = true;
                }

                try {
                    const formData = new FormData(form);
                    formData.append('ci_token', ciToken);

                    const response = await fetch('commercial_update.php', {
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
                        if (result.errors) {
                            applyFormErrors(form, result.errors);
                        }

                        const targetAlert = messageBox || ciAlert;
                        showAlert(targetAlert, result.message || 'Unable to update the commercial invoice.', 'danger');
                        return;
                    }

                    if (result.invoice) {
                        const updated = normaliseCommercialInvoice(result.invoice);
                        if (updated) {
                            const index = state.commercialInvoices.findIndex((item) => item.token === updated.token);
                            if (index >= 0) {
                                state.commercialInvoices[index] = updated;
                            } else {
                                state.commercialInvoices.unshift(updated);
                            }
                        }
                        refreshCiList();
                    }

                    if (result.file_meta) {
                        updateFileMeta(result.file_meta);
                    }

                    showAlert(ciAlert, result.message || 'Commercial invoice updated.', 'success');
                } catch (error) {
                    const targetAlert = messageBox || ciAlert;
                    showAlert(targetAlert, error.message, 'danger');
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

                if (action === 'print-cnf' || action === 'print-bank-forwarding' || action === 'print-toc') {
                    const proforma = state.proformas.find((item) => item.token === piToken);

                    if (!proforma) {
                        showAlert(piAlert, 'Unable to locate the selected proforma invoice.', 'danger');
                        return;
                    }

                    let previewHtml = '';
                    let previewTitle = '';

                    let previewOptions = {};

                    if (action === 'print-cnf') {
                        previewTitle = `C&F Calculation · ${proforma.invoice_number || ''}`;
                        previewHtml = renderCnfPreview(proforma, state);
                    } else if (action === 'print-bank-forwarding') {
                        previewTitle = `Bank Forwarding · ${proforma.invoice_number || ''}`;
                        previewHtml = renderBankForwardingPreview(proforma, state);
                        previewOptions = { styles: buildLetterStyles() };
                    } else {
                        previewTitle = `Table of Contents · ${proforma.invoice_number || ''}`;
                        previewHtml = renderTocPreview(proforma, state);
                        previewOptions = { styles: buildLetterStyles() };
                    }

                    const opened = openPrintPreview(previewTitle, previewHtml, previewOptions);

                    if (!opened) {
                        showAlert(piAlert, 'Preview blocked. Please allow new tabs for this site to open the document.', 'warning');
                    }

                    return;
                }

                if (action === 'add-product') {
                    openProductModal(piToken);
                    return;
                }

                if (action === 'save-freight') {
                    const card = actionButton.closest('.card[data-pi-token]');
                    const input = card ? card.querySelector('[data-freight-input]') : null;
                    const toleranceInput = card ? card.querySelector('[data-tolerance-input]') : null;

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
                        formData.set('tolerance_percentage', toleranceInput ? toleranceInput.value || '' : '');

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
                            throw new Error(result.message || 'Unable to save freight.');
                        }

                        const pi = state.proformas.find((item) => item.token === piToken);
                        if (pi) {
                            pi.freight_amount = result.freight_amount;
                            pi.freight_amount_formatted = result.freight_amount_formatted || result.freight_amount;
                            if (result.tolerance_percentage) {
                                pi.tolerance_percentage = formatToleranceValue(result.tolerance_percentage);
                                pi.tolerance_percentage_formatted = formatToleranceValue(result.tolerance_percentage_formatted || result.tolerance_percentage);
                            }
                        }

                        if (result.file_meta) {
                            updateFileMeta(result.file_meta);
                        }

                        refreshPiList();
                        showAlert(piAlert, result.message || 'Freight saved.', 'success');
                    } catch (error) {
                        showAlert(piAlert, error.message, 'danger');
                    } finally {
                        actionButton.disabled = false;
                        actionButton.innerHTML = originalText;
                    }

                    return;
                }

                if (action === 'save-pi-details') {
                    const card = actionButton.closest('.card[data-pi-token]');

                    if (!card) {
                        return;
                    }

                    const headerInput = card.querySelector('[data-pi-header-input]');
                    const referenceDateInput = card.querySelector('[data-bank-ref-date]');
                    const originalText = actionButton.innerHTML;

                    actionButton.disabled = true;
                    actionButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

                    try {
                        resetAlert(piAlert);
                        const formData = new FormData();
                        formData.set('pi_token', piToken);
                        formData.set('pi_header', headerInput ? headerInput.value : '');
                        formData.set('reference_date', referenceDateInput ? referenceDateInput.value : '');

                        const response = await fetch('proforma_update.php', {
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
                            throw new Error(result.message || 'Unable to save the proforma details.');
                        }

                        if (result.proforma) {
                            const normalised = normaliseProforma(result.proforma);
                            const index = state.proformas.findIndex((item) => item.token === piToken);

                            if (normalised && index !== -1) {
                                const current = state.proformas[index];
                                state.proformas[index] = {
                                    ...current,
                                    pi_header: normalised.pi_header,
                                    reference: normalised.reference,
                                };
                            }
                        }

                        if (result.file_meta) {
                            updateFileMeta(result.file_meta);
                        }

                        refreshPiList();
                        showAlert(piAlert, result.message || 'Proforma details saved.', 'success');
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
                        const result = await deletePiProduct(piToken, productToken);
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
                resetAlert(piProductRemoveAlert);

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

        if (piProductRemoveButton) {
            piProductRemoveButton.addEventListener('click', async () => {
                if (!activePiToken) {
                    showAlert(piProductRemoveAlert, 'Select a proforma invoice before removing products.', 'warning');
                    return;
                }

                if (!piProductSelect || piProductSelect.disabled) {
                    showAlert(piProductRemoveAlert, 'There are no products available to remove.', 'info');
                    return;
                }

                const selectedToken = piProductSelect.value;

                if (!selectedToken) {
                    showAlert(piProductRemoveAlert, 'Choose a product to remove.', 'warning');
                    return;
                }

                if (!window.confirm('Remove the selected product from this proforma invoice?')) {
                    return;
                }

                const originalText = piProductRemoveButton.innerHTML;
                piProductRemoveButton.disabled = true;
                piProductRemoveButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

                try {
                    resetAlert(piProductRemoveAlert);
                    const result = await deletePiProduct(activePiToken, selectedToken);
                    populatePiProductRemovalOptions(activePiToken);
                    showAlert(piProductRemoveAlert, result.message || 'Product removed from the proforma invoice.', 'success');
                    showAlert(piAlert, result.message || 'Product removed from the proforma invoice.', 'success');
                } catch (error) {
                    showAlert(piProductRemoveAlert, error.message, 'danger');
                } finally {
                    piProductRemoveButton.disabled = false;
                    piProductRemoveButton.innerHTML = originalText;
                    populatePiProductRemovalOptions(activePiToken);
                }
            });
        }

    };

    if (state.file) {
        updateFileMeta(state.file);
    }

    populateVendorProductSelect();
    updateLcSummary(state.lc);
    syncLcForm(state.lc);
    updateInsuranceSummary(state.insurance);
    syncInsuranceForm(state.insurance);
    refreshCiList();
    refreshPiList();
    attachEventListeners();
});
