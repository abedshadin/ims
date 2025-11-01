import {
    parseNumber,
    toCurrency,
    formatQuantity,
    formatWeight,
    formatFreight,
    formatToleranceValue,
    formatDate,
} from './formatting.js';

export const parseJson = (element) => {
    try {
        return JSON.parse(element.textContent || '{}');
    } catch (error) {
        return {};
    }
};

export const escapeSelector = (value) => {
    if (window.CSS && typeof window.CSS.escape === 'function') {
        return window.CSS.escape(value);
    }

    return value.replace(/[^a-zA-Z0-9_\-]/g, '\\$&');
};

export const normaliseReference = (reference) => {
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

export const normaliseProforma = (proforma) => {
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

export const normaliseCommercialProduct = (product) => {
    if (!product || typeof product !== 'object') {
        return null;
    }

    const quantityValue = product.final_quantity ?? product.final_quantity_formatted ?? '0';
    const unitPriceValue = product.final_unit_price ?? product.final_unit_price_formatted ?? '0';
    const totalItemValue = product.total_item_price ?? product.total_item_price_formatted ?? '0';
    const unitFreightValue = product.unit_freight ?? product.unit_freight_formatted ?? '0';
    const totalFreightValue = product.total_freight ?? product.total_freight_formatted ?? '0';
    const itemWeightValue = product.item_weight ?? product.item_weight_formatted ?? '0';
    const totalWeightValue = product.total_weight ?? product.total_weight_formatted ?? '0';
    const totalCnfValue = product.total_cnf_value ?? product.total_cnf_value_formatted ?? '0';
    const invoiceTotalValue = product.invoice_total ?? product.invoice_total_formatted ?? '0';

    const finalQuantity = formatQuantity(quantityValue);
    const finalUnitPrice = toCurrency(unitPriceValue);
    const totalItemPrice = toCurrency(totalItemValue);
    const unitFreight = formatFreight(unitFreightValue);
    const totalFreight = toCurrency(totalFreightValue);
    const itemWeight = formatWeight(itemWeightValue);
    const totalWeight = formatWeight(totalWeightValue);
    const totalCnf = toCurrency(totalCnfValue);
    const invoiceTotal = toCurrency(invoiceTotalValue);

    return {
        ...product,
        token: product.token || '',
        proforma_product_token: product.proforma_product_token || '',
        product_name: product.product_name || '',
        brand: product.brand || '',
        country_of_origin: product.country_of_origin || '',
        product_category: product.product_category || '',
        product_size: product.product_size || '',
        unit: product.unit || '',
        hs_code: product.hs_code || '',
        final_quantity: finalQuantity,
        final_quantity_formatted: finalQuantity,
        final_unit_price: finalUnitPrice,
        final_unit_price_formatted: finalUnitPrice,
        total_item_price: totalItemPrice,
        total_item_price_formatted: totalItemPrice,
        unit_freight: unitFreight,
        unit_freight_formatted: unitFreight,
        total_freight: totalFreight,
        total_freight_formatted: totalFreight,
        item_weight: itemWeight,
        item_weight_formatted: itemWeight,
        total_weight: totalWeight,
        total_weight_formatted: totalWeight,
        total_cnf_value: totalCnf,
        total_cnf_value_formatted: totalCnf,
        invoice_total: invoiceTotal,
        invoice_total_formatted: invoiceTotal,
    };
};

export const normaliseCommercialInvoice = (invoice) => {
    if (!invoice || typeof invoice !== 'object') {
        return null;
    }

    const products = Array.isArray(invoice.products)
        ? invoice.products.map(normaliseCommercialProduct).filter((item) => item && item.token)
        : [];

    const invoiceDate = invoice.invoice_date || '';
    const invoiceDateFormatted = invoice.invoice_date_formatted
        || (invoiceDate ? formatDate(invoiceDate, { day: '2-digit', month: 'short', year: 'numeric' }) : '');
    const totalValue = invoice.total_value ?? invoice.total_value_formatted ?? '0';
    const totalValueFormatted = toCurrency(totalValue);

    const proforma = invoice.proforma && typeof invoice.proforma === 'object'
        ? {
            token: invoice.proforma.token || '',
            invoice_number: invoice.proforma.invoice_number || '',
        }
        : { token: '', invoice_number: '' };

    return {
        ...invoice,
        token: invoice.token || '',
        proforma,
        invoice_number: invoice.invoice_number || '',
        invoice_date: invoiceDate,
        invoice_date_formatted: invoiceDateFormatted,
        total_value: totalValueFormatted,
        total_value_formatted: totalValueFormatted,
        products,
    };
};

export const calculateProformaMetrics = (proforma) => {
    const products = Array.isArray(proforma.products) ? proforma.products : [];
    const lines = [];
    let totalWeight = 0;
    let totalFob = 0;
    let totalQuantity = 0;
    let totalCnf = 0;

    products.forEach((product) => {
        const quantity = parseNumber(product.quantity);
        const fobTotal = parseNumber(product.fob_total);
        const productWeight = parseNumber(product.item_weight);

        totalWeight += productWeight;
        totalFob += fobTotal;
        totalQuantity += quantity;

        lines.push({
            product,
            quantity,
            fobTotal,
            productWeight,
        });
    });

    const totalFreight = parseNumber(proforma.freight_amount);
    const freightPerWeight = totalWeight > 0 ? totalFreight / totalWeight : 0;
    const freightAmountDisplay = toCurrency(totalFreight);
    const totalWeightExpressionDisplay = totalWeight > 0 ? formatWeight(totalWeight) : '0';

    lines.forEach((line) => {
        const hasProductWeight = line.productWeight > 0;
        const hasTotalWeight = totalWeight > 0;
        const fobPerUnit = line.quantity > 0 ? line.fobTotal / line.quantity : 0;
        const fobPerWeight = hasProductWeight ? line.fobTotal / line.productWeight : 0;
        const freightShare = hasProductWeight && hasTotalWeight ? line.productWeight * freightPerWeight : 0;
        const freightPerUnit = line.quantity > 0 ? freightShare / line.quantity : 0;
        let cnfPerWeight = 0;
        let cnfTotal = line.fobTotal;
        let cnfPerUnit = fobPerUnit;

        if (hasProductWeight && hasTotalWeight) {
            cnfPerWeight = freightPerWeight + fobPerWeight;
            cnfTotal = cnfPerWeight * line.productWeight;
            cnfPerUnit = line.quantity > 0 ? cnfTotal / line.quantity : 0;
        }

        const productWeightDisplay = hasProductWeight ? formatWeight(line.productWeight) : '0';
        const freightComponentDisplay = formatFreight(freightPerWeight);
        const fobComponentDisplay = formatFreight(fobPerWeight);
        const cnfPerWeightDisplay = formatFreight(cnfPerWeight);
        const fobTotalDisplay = toCurrency(line.fobTotal);
        const calcExpression = (hasProductWeight && hasTotalWeight)
            ? `($${freightAmountDisplay} ÷ ${totalWeightExpressionDisplay}) + ($${fobTotalDisplay} ÷ ${productWeightDisplay}) = $${cnfPerWeightDisplay} per weight`
            : 'Calculation unavailable (missing weight)';
        const calcComponents = (hasProductWeight && hasTotalWeight)
            ? `Freight/Weight $${freightComponentDisplay} + FOB/Weight $${fobComponentDisplay} = $${cnfPerWeightDisplay} per weight`
            : 'Freight or weight data missing for this product';

        line.fobPerUnit = fobPerUnit;
        line.fobPerWeight = fobPerWeight;
        line.freightPerUnit = freightPerUnit;
        line.freightPerWeight = hasTotalWeight ? freightPerWeight : 0;
        line.freightShare = freightShare;
        line.cnfPerUnit = cnfPerUnit;
        line.cnfPerWeight = cnfPerWeight;
        line.cnfTotal = cnfTotal;
        line.cnfCalcExpression = calcExpression;
        line.cnfCalcComponents = calcComponents;

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

export const buildBankReference = (proforma, file = null) => {
    if (proforma && proforma.reference && proforma.reference.code) {
        return `${proforma.reference.code}`;
    }

    if (!file) {
        return 'TFL/SCM/BANK/1';
    }

    if (file.bank_reference) {
        return `${file.bank_reference}`;
    }

    const bankCode = (file.bank_name || 'BANK').toString().toUpperCase();
    const fileName = `${file.file_name || ''}`;
    let sequence = '';

    if (fileName) {
        const segments = fileName.split('/');
        if (segments.length > 0) {
            sequence = segments[segments.length - 1] || '';
        }
    }

    if (!sequence) {
        sequence = `${file.id || file.token || 1}`;
    }

    return `TFL/SCM/${bankCode}/${sequence}`;
};

export const buildInitialState = (element) => {
    const raw = parseJson(element);
    const state = {
        proformas: [],
        commercialInvoices: [],
        vendorProducts: [],
        file: null,
        lc: null,
        bank: null,
        insurance: null,
    };

    state.proformas = Array.isArray(raw.proformas)
        ? raw.proformas.map(normaliseProforma).filter((item) => item)
        : [];
    state.commercialInvoices = Array.isArray(raw.commercialInvoices)
        ? raw.commercialInvoices.map(normaliseCommercialInvoice).filter((item) => item)
        : [];
    state.vendorProducts = Array.isArray(raw.vendorProducts) ? raw.vendorProducts : [];
    state.file = raw.file && typeof raw.file === 'object' ? raw.file : null;
    state.lc = raw.lc && typeof raw.lc === 'object' ? raw.lc : null;
    state.bank = raw.bank && typeof raw.bank === 'object'
        ? raw.bank
        : (state.file && typeof state.file.bank_profile === 'object' ? state.file.bank_profile : null);
    state.insurance = raw.insurance && typeof raw.insurance === 'object' ? raw.insurance : null;

    return state;
};
