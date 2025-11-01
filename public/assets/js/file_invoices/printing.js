import {
    parseNumber,
    toCurrency,
    formatPercent,
    formatToleranceValue,
    formatTolerance,
    formatDate,
    currencyToWords,
    escapeHtml,
    formatMultiline,
} from './formatting.js';
import {
    calculateProformaMetrics,
    normaliseReference,
    buildBankReference,
} from './normalisation.js';

export const buildPrintStyles = () => {
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

export const buildLetterStyles = () => {
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

export const renderPrintHeader = (title, proforma, file) => {
    const vendorName = (file && file.vendor_name) || '';
    const fileName = (file && file.file_name) || '';
    const bankName = (file && file.bank_name) || '';
    const brand = (file && file.brand) || '';
    const createdAt = proforma.created_at_human || '';
    const reference = normaliseReference(proforma.reference);
    const referenceCode = reference.code || buildBankReference(proforma, file);
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

export const openPrintPreview = (title, content, options = {}) => {
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
        // Ignore
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

export const renderCnfPreview = (proforma, state) => {
    const metrics = calculateProformaMetrics(proforma);
    const file = state.file || {};
    let totalAssesValue = 0;
    let totalCnf = 0;
    let totalQuantity = 0;

    const rows = metrics.lines.map((line, index) => {
        const quantity = line.quantity;
        const assesUnit = parseNumber(line.product.asses_unit_price);
        const assesValue = assesUnit * quantity;
        const cnfTotal = line.cnfTotal || 0;
        const cnfPerUnit = quantity > 0 ? cnfTotal / quantity : 0;
        const percentChange = assesUnit > 0 ? ((cnfPerUnit - assesUnit) / assesUnit) * 100 : 0;

        totalAssesValue += assesValue;
        totalCnf += cnfTotal;
        totalQuantity += quantity;

        return `
            <tr>
                <td class="text-center">${index + 1}</td>
                <td>${escapeHtml(line.product.product_name || '')}</td>
                <td class="text-end">$${toCurrency(assesValue)}</td>
                <td class="text-end">$${toCurrency(cnfPerUnit)}</td>
                <td class="text-end">${formatPercent(percentChange)}</td>
            </tr>
        `;
    }).join('') || `
        <tr>
            <td colspan="5" class="text-center muted">No products available for this proforma invoice.</td>
        </tr>
    `;

    const totalAssesPerUnit = totalQuantity > 0 ? totalAssesValue / totalQuantity : 0;
    const totalCnfPerUnit = totalQuantity > 0 ? totalCnf / totalQuantity : 0;
    const totalPercent = totalAssesPerUnit > 0
        ? ((totalCnfPerUnit - totalAssesPerUnit) / totalAssesPerUnit) * 100
        : 0;

    return `
        ${renderPrintHeader('C&amp;F Calculation Summary', proforma, file)}
        <table>
            <thead>
                <tr>
                    <th class="text-center" style="width: 8%">Serial No</th>
                    <th>Product Name</th>
                    <th class="text-end" style="width: 18%">Asses Value</th>
                    <th class="text-end" style="width: 18%">C&amp;F Per Unit</th>
                    <th class="text-end" style="width: 18%">% Change</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
            <tfoot>
                <tr>
                    <td colspan="2" class="text-end">Totals</td>
                    <td class="text-end">$${toCurrency(totalAssesValue)}</td>
                    <td class="text-end">$${toCurrency(totalCnfPerUnit)}</td>
                    <td class="text-end">${formatPercent(totalPercent)}</td>
                </tr>
            </tfoot>
        </table>
        <div class="muted">Percentage change compares calculated C&amp;F per unit against assessed values per unit; assessed value column displays total assessed amounts.</div>
    `;
};

export const renderBankForwardingPreview = (proforma, state) => {
    const metrics = calculateProformaMetrics(proforma);
    const file = state.file || {};

    const bankInfo = state.bank && typeof state.bank === 'object' ? state.bank : (file.bank_profile || {});
    const bankName = bankInfo.name || file.bank_name || 'BANK NAME';
    const bankAddressLines = Array.isArray(bankInfo.address_lines)
        ? bankInfo.address_lines.filter((line) => line && line.trim().length > 0)
        : [];
    const bankAddressHtml = bankAddressLines.length > 0
        ? bankAddressLines.map((line) => `${escapeHtml(line)}<br>`).join('')
        : 'Address Line 1<br>Address Line 2<br>Address Line 3';
    const reference = normaliseReference(proforma.reference);
    const referenceNo = reference.code || buildBankReference(proforma, file);
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

export const renderTocPreview = (proforma, state) => {
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

    const toleranceValue = formatToleranceValue(proforma.tolerance_percentage ?? proforma.tolerance_percentage_formatted ?? 0);
    const toleranceLine = Number.parseFloat(toleranceValue) > 0
        ? `<li>L/C allows ${escapeHtml(formatTolerance(toleranceValue))} tolerance in amount & qty.</li>`
        : '';

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
                    ${toleranceLine}
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
