const currencySymbols = {
    USD: '$',
    EURO: '€',
};

export const parseNumber = (value) => {
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

export const toCurrency = (value) => {
    return parseNumber(value).toFixed(2);
};

export const getCurrencySymbol = (currency) => {
    return currencySymbols[currency] || '$';
};

export const formatQuantity = (value) => {
    const number = parseNumber(value);

    if (!Number.isFinite(number) || number === 0) {
        return '0';
    }

    const fixed = number.toFixed(3);
    return fixed.replace(/\.0+$/, '').replace(/\.([0-9]*[1-9])0+$/, '.$1');
};

export const formatWeight = (value) => {
    const number = parseNumber(value);

    if (!Number.isFinite(number) || number === 0) {
        return '0';
    }

    return number.toFixed(3).replace(/\.0+$/, '').replace(/\.([0-9]*[1-9])0+$/, '.$1');
};

export const formatFreight = (value) => {
    const number = parseNumber(value);

    if (!Number.isFinite(number) || number === 0) {
        return '0.0000';
    }

    return number.toFixed(4);
};

export const formatPercent = (value) => {
    const number = typeof value === 'number' ? value : Number.parseFloat(`${value}`);

    if (!Number.isFinite(number)) {
        return '0.00%';
    }

    const sign = number > 0 ? '+' : '';
    return `${sign}${number.toFixed(2)}%`;
};

export const formatToleranceValue = (value) => {
    const number = parseNumber(value);

    if (!Number.isFinite(number) || number < 0) {
        return '0';
    }

    return number.toFixed(0);
};

export const formatTolerance = (value) => `${formatToleranceValue(value)}%`;

export const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value;
    return div.innerHTML;
};

export const parseDateValue = (value) => {
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

export const formatDate = (value, options) => {
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

export const numberToWords = (value) => {
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

export const currencyToWords = (value, currencyName = 'Dollars', centName = 'Cents') => {
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

export const formatMultiline = (value) => {
    if (!value) {
        return '';
    }

    return escapeHtml(`${value}`).replace(/\r?\n/g, '<br>');
};
