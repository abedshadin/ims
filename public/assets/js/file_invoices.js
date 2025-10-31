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

    const normaliseReference = (reference) => {
        if (!reference || typeof reference !== 'object') {
            return {
                code: '',
                date: '',
                date_formatted: '',
            };
        }

        return {
            code: reference.code || '',
            date: reference.date || '',
            date_formatted: reference.date_formatted || '',
        };
    };

    const normaliseProforma = (proforma) => {
        if (!proforma || typeof proforma !== 'object') {
            return null;
        }

        const products = Array.isArray(proforma.products) ? proforma.products : [];
        const toleranceValue = proforma.tolerance_percentage ?? proforma.tolerance_percentage_formatted ?? '0';
        const toleranceDisplay = proforma.tolerance_percentage_formatted ?? toleranceValue;

        return {
            ...proforma,
            pi_header: proforma.pi_header || '',
            freight_amount: proforma.freight_amount || proforma.freight_amount_formatted || '0.00',
            freight_amount_formatted: proforma.freight_amount_formatted || proforma.freight_amount || '0.00',
            tolerance_percentage: formatToleranceValue(toleranceValue),
            tolerance_percentage_formatted: formatToleranceValue(toleranceDisplay),
            products,
            reference: normaliseReference(proforma.reference),
        };
    };

    const state = parseJson(dataElement);
    state.proformas = Array.isArray(state.proformas)
        ? state.proformas.map(normaliseProforma).filter((item) => item)
        : [];
    state.vendorProducts = Array.isArray(state.vendorProducts) ? state.vendorProducts : [];
    state.file = state.file && typeof state.file === 'object' ? state.file : null;
    state.lc = state.lc && typeof state.lc === 'object' ? state.lc : null;
    state.bank = state.bank && typeof state.bank === 'object'
        ? state.bank
        : (state.file && typeof state.file.bank_profile === 'object' ? state.file.bank_profile : null);

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

    function parseNumber(value) {
        if (typeof value === 'number') {
            return Number.isFinite(value) ? value : 0;
        }

        if (value === null || value === undefined) {
            return 0;
        }

        const sanitised = String(value).replace(/[^0-9+\-.,]/g, '').replace(/,/g, '');
        const parsed = Number.parseFloat(sanitised);

        return Number.isNaN(parsed) ? 0 : parsed;
    }

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

    const formatPercent = (value) => {
        const number = typeof value === 'number' ? value : Number.parseFloat(`${value}`);

        if (!Number.isFinite(number)) {
            return '0.00%';
        }

        const sign = number > 0 ? '+' : '';
        return `${sign}${number.toFixed(2)}%`;
    };

    function formatToleranceValue(value) {
        const number = parseNumber(value);

        if (!Number.isFinite(number) || number < 0) {
            return '0.00';
        }

        return number.toFixed(2);
    }

    const formatTolerance = (value) => `${formatToleranceValue(value)}%`;

    const getBankProfile = () => {
        if (state.bank && typeof state.bank === 'object') {
            return state.bank;
        }

        if (state.file && typeof state.file.bank_profile === 'object') {
            return state.file.bank_profile;
        }

        return null;
    };

    const buildBankReference = (proforma = null) => {
        if (proforma && proforma.reference && proforma.reference.code) {
            return `${proforma.reference.code}`;
        }

        if (!state.file) {
            return 'TFL/SCM/BANK/1';
        }

        if (state.file.bank_reference) {
            return `${state.file.bank_reference}`;
        }

        const bankCode = (state.file.bank_name || 'BANK').toString().toUpperCase();
        const fileName = `${state.file.file_name || ''}`;
        let sequence = '';

        if (fileName) {
            const segments = fileName.split('/');
            if (segments.length > 0) {
                sequence = segments[segments.length - 1] || '';
            }
        }

        if (!sequence) {
            sequence = `${state.file.id || state.file.token || 1}`;
        }

        return `TFL/SCM/${bankCode}/${sequence}`;
    };

    const parseDateValue = (value) => {
        if (!value) {
            return null;
        }

        if (value instanceof Date) {
            return Number.isNaN(value.getTime()) ? null : value;
        }

        const stringValue = `${value}`.trim();

        if (stringValue === '') {
            return null;
        }

        const normalised = stringValue.replace(' ', 'T');
        let date = new Date(normalised);

        if (Number.isNaN(date.getTime())) {
            date = new Date(`${normalised}Z`);
        }

        if (Number.isNaN(date.getTime())) {
            return null;
        }

        return date;
    };

    const formatDate = (value, options) => {
        const date = parseDateValue(value);

        if (!date) {
            return '';
        }

        try {
            return date.toLocaleDateString('en-GB', options || { day: '2-digit', month: 'short', year: 'numeric' });
        } catch (error) {
            return '';
        }
    };

    const numberWords = {
        ones: ['Zero', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'],
        tens: ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'],
        scales: ['', 'Thousand', 'Million', 'Billion', 'Trillion'],
    };

    const convertHundreds = (number) => {
        let words = '';
        const hundreds = Math.floor(number / 100);
        const remainder = number % 100;

        if (hundreds > 0) {
            words += `${numberWords.ones[hundreds]} Hundred`;
            if (remainder > 0) {
                words += ' ';
            }
        }

        if (remainder > 0) {
            if (remainder < 20) {
                words += numberWords.ones[remainder];
            } else {
                const tens = Math.floor(remainder / 10);
                const units = remainder % 10;
                words += numberWords.tens[tens];
                if (units > 0) {
                    words += `-${numberWords.ones[units]}`;
                }
            }
        }

        return words;
    };

    const numberToWords = (value) => {
        const number = Math.floor(Math.abs(value));

        if (!Number.isFinite(number) || number === 0) {
            return numberWords.ones[0];
        }

        let remaining = number;
        let scaleIndex = 0;
        const parts = [];

        while (remaining > 0) {
            const chunk = remaining % 1000;

            if (chunk > 0) {
                const chunkWords = convertHundreds(chunk);
                const scaleWord = numberWords.scales[scaleIndex] || '';
                parts.unshift(`${chunkWords}${scaleWord ? ` ${scaleWord}` : ''}`.trim());
            }

            remaining = Math.floor(remaining / 1000);
            scaleIndex += 1;
        }

        return parts.join(' ').trim();
    };

    const currencyToWords = (value, currencyName = 'Dollars', centName = 'Cents') => {
        const amount = parseNumber(value);
        const absolute = Math.abs(amount);
        const whole = Math.floor(absolute);
        const fraction = Math.round((absolute - whole) * 100);

        const wholeWords = numberToWords(whole);
        let result = `${wholeWords} ${currencyName}`.trim();

        if (fraction > 0) {
            const centWords = numberToWords(fraction);
            result = `${result} and ${centWords} ${centName}`;
        }

        return result || `${numberWords.ones[0]} ${currencyName}`;
    };

    const formatMultiline = (value) => {
        if (!value) {
            return '';
        }

        return escapeHtml(`${value}`).replace(/\r?\n/g, '<br>');
    };

    const buildPrintStyles = () => {
        return `
            :root { color-scheme: only light; }
            @page { size: A4; margin: 14mm; }
            body { font-family: 'Segoe UI', Tahoma, sans-serif; color: #212529; background: #f8f9fa; margin: 0; padding: 0 0 2rem; }
            h1 { margin-bottom: 0.25rem; }
            h2 { margin-top: 0; font-size: 1.05rem; color: #6c757d; }
            .print-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; gap: 1rem; }
            .print-header .meta { display: grid; gap: 0.35rem; font-size: 0.9rem; }
            .print-header .meta span { font-weight: 600; color: #495057; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; background: #fff; }
            th, td { padding: 0.55rem 0.75rem; border: 1px solid #dee2e6; text-align: left; }
            th { background: #e9ecef; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.03em; }
            tfoot td { font-weight: 600; background: #f1f3f5; }
            .text-end { text-align: right; }
            .text-center { text-align: center; }
            .muted { color: #6c757d; font-size: 0.85rem; }
            @media print {
                body { background: #fff; padding: 0; }
                .print-actions { display: none !important; }
            }
        `;
    };

    const buildLetterStyles = () => {
        return `
            :root { color-scheme: only light; }
            @page { size: A4; margin: 0; }
            body { font-family: 'Times New Roman', serif; background: #f8f9fa; color: #1f2933; margin: 0; padding: 0; }
            .letter-page { width: 210mm; min-height: 297mm; max-height: 297mm; margin: 0 auto 1.5rem; background: #fff; box-sizing: border-box; padding: 16mm 18mm; display: flex; flex-direction: column; border: 1px solid #e9ecef; box-shadow: 0 0.5rem 1.5rem rgba(15, 23, 42, 0.08); overflow: hidden; }
            .letter-header, .letter-footer { flex: 0 0 auto; }
            .letter-header img, .letter-footer img { display: block; width: 100%; height: auto; }
            .letter-body { flex: 1 1 auto; display: flex; flex-direction: column; gap: 0.75rem; }
            .letter-body p { margin-bottom: 0.75rem; }
            .bank-letter-body .ref-no { font-weight: 600; margin-bottom: 0.35rem; }
            .bank-letter-body .subject { margin-top: 0.75rem; margin-bottom: 1.1rem; }
            .closing { margin-top: auto; }
            .sig-row { display: flex; justify-content: space-between; gap: 2.5rem; font-weight: 600; margin-top: 2.5rem; }
            .sig { flex: 1; }
            .doc-title { font-size: 1.1rem; font-weight: 700; text-align: center; margin-bottom: 1rem; text-transform: uppercase; }
            ol { margin: 0; padding-left: 1.1rem; }
            ol li { margin-bottom: 0.55rem; text-align: justify; }
            @media print {
                body { background: #fff; }
                .letter-page { margin: 0 auto; box-shadow: none; border: none; height: 297mm; max-height: 297mm; overflow: hidden; page-break-after: avoid; break-after: avoid; }
                .letter-page + .letter-page { page-break-before: always; break-before: page; }
                .print-actions { display: none !important; }
            }
        `;
    };

    const renderPrintHeader = (title, proforma, file) => {
        const vendorName = (file && file.vendor_name) || '';
        const fileName = (file && file.file_name) || '';
        const bankName = (file && file.bank_name) || '';
        const brand = (file && file.brand) || '';
        const createdAt = proforma.created_at_human || '';
        const reference = normaliseReference(proforma.reference);
        const referenceCode = reference.code || buildBankReference(proforma);
        const referenceDate = reference.date
            ? formatDate(reference.date, { day: '2-digit', month: 'short', year: 'numeric' })
            : '';

        return `
            <div class="print-header">
                <div>
                    <h1>${escapeHtml(title)}</h1>
                    <h2>Proforma Invoice ${escapeHtml(proforma.invoice_number || '')}</h2>
                    <div class="muted">Created ${escapeHtml(createdAt)}</div>
                </div>
                <div class="meta">
                    <div><span>Vendor:</span> ${escapeHtml(vendorName)}</div>
                    <div><span>File Ref:</span> ${escapeHtml(fileName)}</div>
                    <div><span>Bank:</span> ${escapeHtml(bankName)}</div>
                    <div><span>Brand:</span> ${escapeHtml(brand)}</div>
                    <div><span>Bank Ref:</span> ${escapeHtml(referenceCode)}</div>
                    ${referenceDate ? `<div><span>Ref Date:</span> ${escapeHtml(referenceDate)}</div>` : ''}
                </div>
            </div>
        `;
    };

    const openPrintPreview = (title, content, options = {}) => {
        const baseStyles = buildPrintStyles();
        const extraStyles = options.styles || '';
        const baseHref = document.baseURI || window.location.href;
        const previewWindow = window.open('', '_blank');

        if (!previewWindow) {
            return false;
        }

        try {
            previewWindow.opener = null;
        } catch (error) {
            // Ignore errors if the browser blocks modifying opener.
        }

        const html = `
            <!DOCTYPE html>
            <html lang="en">
                <head>
                    <meta charset="utf-8">
                    <title>${title}</title>
                    <base href="${baseHref}">
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
                    <style>${baseStyles}${extraStyles}</style>
                </head>
                <body class="bg-light">
                    <div class="print-actions position-sticky top-0 d-print-none d-flex gap-2 p-3">
                        <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="window.close()">Close</button>
                    </div>
                    ${content}
                </body>
            </html>
        `;

        previewWindow.document.open();
        previewWindow.document.write(html);
        previewWindow.document.close();
        previewWindow.focus();

        return true;
    };

    const renderCnfPreview = (proforma) => {
        const metrics = calculateProformaMetrics(proforma);
        const file = state.file || {};
        let totalAssesValue = 0;
        let totalCnf = 0;

        const rows = metrics.lines.map((line, index) => {
            const quantity = line.quantity;
            const assesUnit = parseNumber(line.product.asses_unit_price);
            const assesValue = assesUnit * quantity;
            const cnfTotal = line.cnfTotal || 0;
            const percentChange = assesValue > 0 ? ((cnfTotal - assesValue) / assesValue) * 100 : 0;

            totalAssesValue += assesValue;
            totalCnf += cnfTotal;

            return `
                <tr>
                    <td class="text-center">${index + 1}</td>
                    <td>${escapeHtml(line.product.product_name || '')}</td>
                    <td class="text-end">$${toCurrency(assesValue)}</td>
                    <td class="text-end">$${toCurrency(cnfTotal)}</td>
                    <td class="text-end">${formatPercent(percentChange)}</td>
                </tr>
            `;
        }).join('') || `
            <tr>
                <td colspan="5" class="text-center muted">No products available for this proforma invoice.</td>
            </tr>
        `;

        const totalPercent = totalAssesValue > 0 ? ((totalCnf - totalAssesValue) / totalAssesValue) * 100 : 0;

        return `
            ${renderPrintHeader('C&amp;F Calculation Summary', proforma, file)}
            <table>
                <thead>
                    <tr>
                        <th class="text-center" style="width: 8%">Serial No</th>
                        <th>Product Name</th>
                        <th class="text-end" style="width: 18%">Asses Value</th>
                        <th class="text-end" style="width: 18%">Calculated C&amp;F</th>
                        <th class="text-end" style="width: 18%">% Change</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="text-end">Totals</td>
                        <td class="text-end">$${toCurrency(totalAssesValue)}</td>
                        <td class="text-end">$${toCurrency(totalCnf)}</td>
                        <td class="text-end">${formatPercent(totalPercent)}</td>
                    </tr>
                </tfoot>
            </table>
            <div class="muted">Percentage change compares calculated C&amp;F totals against assessed values.</div>
        `;
    };

    const renderBankForwardingPreview = (proforma) => {
        const metrics = calculateProformaMetrics(proforma);
        const file = state.file || {};

        const bankInfo = getBankProfile() || {};
        const bankName = bankInfo.name || file.bank_name || 'BANK NAME';
        const bankAddressLines = Array.isArray(bankInfo.address_lines)
            ? bankInfo.address_lines.filter((line) => line && line.trim().length > 0)
            : [];
        const bankAddressHtml = bankAddressLines.length > 0
            ? bankAddressLines.map((line) => `${escapeHtml(line)}<br>`).join('')
            : 'Address Line 1<br>Address Line 2<br>Address Line 3';
        const reference = normaliseReference(proforma.reference);
        const referenceNo = reference.code || buildBankReference(proforma);
        const referenceDateDisplay = reference.date
            ? formatDate(reference.date, { day: '2-digit', month: 'long', year: 'numeric' })
            : formatDate(new Date(), { day: '2-digit', month: 'long', year: 'numeric' });
        const vendorName = file.vendor_name || 'VENDOR NAME';
        const vendorAddressHtml = formatMultiline(file.vendor_address || '') || 'VENDOR ADDRESS';
        const baseSubject = ((state.lc && state.lc.subject_line) || 'Opening L/C for Import').trim();
        const headerSuffix = (proforma.pi_header || '').trim();
        const subjectLine = headerSuffix ? `${baseSubject} ${headerSuffix}`.trim() : baseSubject;
        const currencySymbol = (state.file && state.file.default_currency) || 'US$';
        const grandTotal = metrics.totalCnf;
        const totalInWords = `${currencyToWords(grandTotal, 'US Dollars', 'Cents')} Only`;
        const accountNumberRaw = bankInfo.account_number || (state.file && (state.file.bank_account_number || state.file.beneficiary_bank_account)) || '';
        const accountName = bankInfo.account_name || '';
        const accountNumber = accountName
            ? `${accountNumberRaw || 'ACCOUNT_NUMBER_NOT_FOUND'} (${accountName})`
            : accountNumberRaw;

        return `
            <div class="letter-page d-flex flex-column">
                <header class="letter-header mb-3"><img src="header.jpg" alt="Header"></header>
                <main class="letter-body bank-letter-body" role="main">
                    <p class="ref-no mb-1">Ref: ${escapeHtml(referenceNo)}</p>
                    <p class="date mb-3">${escapeHtml(referenceDateDisplay || '')}</p>

                    <div class="address-block mb-3">
                        <p class="mb-0"><strong>${escapeHtml(bankName)}</strong><br>
                        ${bankAddressHtml}</p>
                    </div>

                    <p class="attn text-uppercase fw-semibold">Attn: Trade Service (Import)</p>

                    <p class="subject fw-semibold">Sub: ${escapeHtml(subjectLine)}</p>

                    <p class="mb-3" style="text-align: justify;">
                        We are enclosing L/C application form and other related papers duly filled in, stamped and
                        signed by us for opening L/C worth <strong>${escapeHtml(currencySymbol)} ${toCurrency(grandTotal)}
                        (${escapeHtml(totalInWords)})</strong>
                        only favoring <strong>${escapeHtml(vendorName)}</strong>,
                        <strong>${vendorAddressHtml}</strong>.
                    </p>

                    <p>
                        Please register L/C & request to debit our current account no.
                        <strong>${escapeHtml(accountNumber || 'ACCOUNT_NUMBER_NOT_FOUND')}</strong>
                        maintained with you for your margin and charges.
                    </p>

                    <div class="closing">
                        <p>Thanking You,</p>
                        <p>Yours Faithfully,<br>
                        For <strong>TRANSCOM FOODS LIMITED</strong></p>
                    </div>

                    <div class="sig-row">
                        <div class="sig text-start">Authorized Signature</div>
                        <div class="sig text-end">Authorized Signature</div>
                    </div>

                </main>
                <footer class="letter-footer mt-auto pt-3"><img src="footer.jpg" alt="Footer"></footer>
            </div>
        `;
    };

    const renderTocPreview = (proforma) => {
        const metrics = calculateProformaMetrics(proforma);
        const file = state.file || {};

        const currencySymbol = (state.file && state.file.default_currency) || 'US$';
        const freightCost = metrics.totalFreight;
        const grandTotal = metrics.totalCnf;
        const piDate = formatDate(proforma.created_at, { day: '2-digit', month: 'short', year: 'numeric' }) || 'N/A';

        const hsCodes = new Set();
        const productNames = new Set();

        const productsOnInvoice = metrics.lines.map((line) => {
            const unit = line.product.unit || '';
            const description = line.product.product_name || '';
            const hsCode = line.product.hs_code || '';

            if (hsCode) {
                hsCodes.add(hsCode);
            }

            if (description) {
                productNames.add(description);
            }

            return {
                description,
                quantity: line.quantity,
                unit,
                unitPrice: line.cnfPerUnit || 0,
            };
        });

        const productDescriptions = productsOnInvoice.length > 0
            ? productsOnInvoice.map((product) => {
                const quantityDisplay = formatQuantity(product.quantity);
                const unitLower = (product.unit || '').toLowerCase();
                const hasUnit = unitLower !== 'none' && unitLower !== '';
                const description = escapeHtml(product.description || '');
                const unitDisplay = escapeHtml(product.unit || '');
                const amountDisplay = toCurrency(product.unitPrice);

                if (hasUnit) {
                    return `<strong>${escapeHtml(quantityDisplay)} ${unitDisplay} of ${description}</strong> at the rate of ${escapeHtml(currencySymbol)} ${amountDisplay}/${unitDisplay};`;
                }

                return `<strong>${description}</strong>;`;
            }).join(' ')
            : 'Description of goods to follow.';

        const hsCodeString = Array.from(hsCodes).join(', ') || 'N/A';
        const productNamesString = Array.from(productNames).join(', ') || 'products';

        const advisingBankName = file.advising_bank_name || '';
        const advisingSwift = file.advising_swift_code || '';
        const advisingAccount = file.advising_bank_account || '';
        const beneficiaryBank = file.beneficiary_bank_name || '';
        const beneficiarySwift = file.beneficiary_swift || '';
        const beneficiaryAccount = file.beneficiary_bank_account || '';

        const lcLine = advisingBankName
            ? `Please arrange to through L/C to <strong>${escapeHtml(advisingBankName)}, SWIFT CODE: ${escapeHtml(advisingSwift || 'N/A')}, A/C NO. ${escapeHtml(advisingAccount || 'N/A')}</strong>; For Payment to <strong>${escapeHtml(beneficiaryBank || 'Beneficiary Bank')}, SWIFT CODE: ${escapeHtml(beneficiarySwift || 'N/A')}, ${escapeHtml(file.vendor_name || 'VENDOR NAME')}, A/C NO. ${escapeHtml(beneficiaryAccount || 'N/A')}</strong>.`
            : `Please open irrevocable L/C through <strong>${escapeHtml(beneficiaryBank || 'Beneficiary Bank')}, SWIFT Code: ${escapeHtml(beneficiarySwift || 'N/A')}.</strong>`;

        return `
            <div class="letter-page d-flex flex-column">
                <header class="letter-header mb-3"><img src="header.jpg" alt="Header"></header>
                <main class="letter-body" role="main">
                    <div class="doc-title">Other Terms &amp; Conditions</div>
                    <ol class="ps-3">
                        <li>${lcLine}</li>
                        <li>All Packets / Cartons must show <strong>Date of Manufacture &amp; Expiry</strong>.</li>
                        <li>L/C number and date must appear in all shipping documents.</li>
                        <li>L/C number and date, &amp; H.S. Code no. ${escapeHtml(hsCodeString)} must appear in the Invoice.</li>
                        <li>
                            Description of Goods: ${productDescriptions}
                            Freight ${escapeHtml(currencySymbol)} ${toCurrency(freightCost)}; Total Amount ${escapeHtml(currencySymbol)} ${toCurrency(grandTotal)} as per Proforma Invoice No. ${escapeHtml(proforma.invoice_number || 'N/A')} Dated ${escapeHtml(piDate)}.
                        </li>
                        <li>Certificate of Origin issued by Chamber of Commerce.</li>
                        <li>
                            The acceptable highest level of radioactivity has been determined to
                            <strong>50 BQ/KG/CS-137</strong> for imported
                            <strong>${escapeHtml(productNamesString)}</strong>.
                            The radioactivity testing report from the competent authority must be sent along with the shipping documents. The level of radioactivity in <strong>CS-137/KG</strong>
                            should be mentioned quantitatively in the test report.
                        </li>
                        <li>
                            The Certificates mentioning that the Goods Exported are <strong>“Fit for Human Consumption”</strong>, <strong>“Not Harmful to Human Health”</strong>, <strong>“Free From Harmful Substances”</strong> and <strong>“Free From All Harmful Germs”</strong> to be issued by the concerned authority of the Government of the Exporting Country should be sent separately with the shipping documents.
                        </li>
                        <li>Importer’s Name: Transcom Foods Limited, Address: SE (F) 5, Gulshan Avenue, Gulshan, Dhaka-1212, Bangladesh and E-TIN No. 892580838781, must be clearly mentioned / printed in the packets/cartons.</li>
                        <li>E-TIN No. 892580838781, BIN No. 000002132-0101 must appear in the invoice and packing list.</li>
                        <li>The beneficiary must send the shipment advice to Reliance Insurance Ltd. at their E-mail ID: <a href="mailto:info@reliance.com.bd">info@reliance.com.bd</a>.</li>
                    </ol>
                    <div class="sig-row mt-auto">
                        <div class="sig text-start">Authorized Signature</div>
                        <div class="sig text-end">Authorized Signature</div>
                    </div>
                </main>
                <footer class="letter-footer mt-auto pt-3"><img src="footer.jpg" alt="Footer"></footer>
            </div>
        `;
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
            setFieldText('latest_shipment_date_human', state.lc.latest_shipment_date_human || state.lc.latest_shipment_date, '—');
            setFieldText('expiry_date_human', state.lc.expiry_date_human || state.lc.expiry_date, '—');

            const amountElement = getLcFieldElement('lc_amount');
            if (amountElement) {
                const amountValue = state.lc.lc_amount && state.lc.lc_amount !== ''
                    ? toCurrency(state.lc.lc_amount)
                    : toCurrency(state.lc.lc_amount_formatted || '0');
                amountElement.textContent = `$${amountValue}`;
            }
        } else {
            setFieldText('lc_number', '', '—');
            setFieldText('lc_type', '', '—');
            setFieldText('subject_line', '', '—');
            setFieldText('lc_date_human', '', '—');
            setFieldText('latest_shipment_date_human', '', '—');
            setFieldText('expiry_date_human', '', '—');

            const amountElement = getLcFieldElement('lc_amount');
            if (amountElement) {
                amountElement.textContent = '$0.00';
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
        const lineWeightDisplay = formatWeight(line.lineWeight);
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
                <div class="text-muted small">Total Wt: ${escapeHtml(lineWeightDisplay)}</div>
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
        const wrapper = document.createElement('div');
        wrapper.className = 'col-12';

        const card = document.createElement('div');
        card.className = 'workspace-section-card card shadow-sm border-0';
        card.dataset.piToken = proforma.token || '';

        const createdAt = proforma.created_at_human || '';
        const piHeaderValue = proforma.pi_header || '';
        const reference = normaliseReference(proforma.reference);
        const referenceDateFormatted = reference.date_formatted
            || (reference.date ? formatDate(reference.date, { day: '2-digit', month: 'short', year: 'numeric' }) : '');
        const freightValue = toCurrency(proforma.freight_amount || metrics.totalFreight || 0);
        const toleranceValue = formatToleranceValue(proforma.tolerance_percentage);
        const toleranceDisplay = formatTolerance(toleranceValue);
        const totalWeightDisplay = formatWeight(metrics.totalWeight);
        const freightPerWeightDisplay = parseNumber(metrics.freightPerWeight).toFixed(4);
        const totalFobDisplay = toCurrency(metrics.totalFob);
        const totalCnfDisplay = toCurrency(metrics.totalCnf);

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
                                <input class="form-control form-control-sm" type="text" value="${escapeHtml(reference.code)}" data-bank-reference readonly>
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
                    <div class="workspace-stat">
                        <span class="workspace-stat-label">Total FOB</span>
                        <span class="workspace-stat-value">$${escapeHtml(totalFobDisplay)}</span>
                    </div>
                    <div class="workspace-stat">
                        <span class="workspace-stat-label">Total C&amp;F</span>
                        <span class="workspace-stat-value">$${escapeHtml(totalCnfDisplay)}</span>
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
                        previewHtml = renderCnfPreview(proforma);
                    } else if (action === 'print-bank-forwarding') {
                        previewTitle = `Bank Forwarding · ${proforma.invoice_number || ''}`;
                        previewHtml = renderBankForwardingPreview(proforma);
                        previewOptions = { styles: buildLetterStyles() };
                    } else {
                        previewTitle = `Table of Contents · ${proforma.invoice_number || ''}`;
                        previewHtml = renderTocPreview(proforma);
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
    refreshPiList();
    attachEventListeners();
});
