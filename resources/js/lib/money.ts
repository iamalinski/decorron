import Big from 'big.js';

/**
 * Decimal аритметика и форматиране за България.
 *
 * Стойностите се пазят като низове (точни decimal), а не като JS number,
 * за да няма загуба на точност. Форматирането е само за показване.
 */

// Достатъчно десетични знаци за единични цени; закръгляване half-up.
Big.DP = 12;
Big.RM = Big.roundHalfUp;

export const MONEY_SCALE = 2;
export const UNIT_SCALE = 6;

export type Numeric = string | number | Big;

export function big(value: Numeric | null | undefined): Big {
    if (value === null || value === undefined || value === '') {
        return new Big(0);
    }

    try {
        return value instanceof Big ? value : new Big(String(value).replace(',', '.'));
    } catch {
        return new Big(0);
    }
}

/** Валидно ли е числово поле (приема и десетична запетая). */
export function isNumeric(value: string | number | null | undefined): boolean {
    if (value === null || value === undefined || String(value).trim() === '') {
        return false;
    }

    return /^-?\d+([.,]\d+)?$/.test(String(value).trim());
}

/** Низ с фиксиран брой знаци, подходящ за изпращане към backend-а. */
export function toFixedString(value: Numeric, scale = MONEY_SCALE): string {
    return big(value).toFixed(scale);
}

const numberFormatters = new Map<string, Intl.NumberFormat>();

function formatter(options: Intl.NumberFormatOptions): Intl.NumberFormat {
    const key = JSON.stringify(options);
    let instance = numberFormatters.get(key);

    if (!instance) {
        instance = new Intl.NumberFormat('bg-BG', options);
        numberFormatters.set(key, instance);
    }

    return instance;
}

/** „150,33 €“ */
export function formatMoney(value: Numeric | null | undefined, currency = 'EUR'): string {
    return formatter({
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(big(value).toFixed(MONEY_SCALE)));
}

/** „150,33“ без валутен символ. */
export function formatDecimal(value: Numeric | null | undefined, scale = MONEY_SCALE): string {
    return formatter({
        minimumFractionDigits: scale,
        maximumFractionDigits: scale,
    }).format(Number(big(value).toFixed(scale)));
}

/**
 * Единична цена: показва до 6 знака, но маха излишните нули,
 * така че „1,000000“ се показва като „1,00“, а „3,333333“ остава цяло.
 */
export function formatUnitPrice(value: Numeric | null | undefined, currency = 'EUR'): string {
    const amount = big(value);
    const trimmed = amount.toFixed(UNIT_SCALE).replace(/0+$/, '');
    const decimals = Math.max(MONEY_SCALE, trimmed.split('.')[1]?.length ?? 0);

    return formatter({
        style: 'currency',
        currency,
        minimumFractionDigits: MONEY_SCALE,
        maximumFractionDigits: Math.min(decimals, UNIT_SCALE),
    }).format(Number(amount.toFixed(UNIT_SCALE)));
}

/** „40,0%“ */
export function formatPercent(fraction: Numeric | null | undefined, decimals = 1): string {
    return formatter({
        style: 'percent',
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(Number(big(fraction).toFixed(6)));
}

/** „24.10.2026 г.“ */
export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('bg-BG', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date);
}

/** Днешната дата в ISO формат (YYYY-MM-DD), по местно време. */
export function today(): string {
    const now = new Date();
    const offset = now.getTimezoneOffset() * 60000;

    return new Date(now.getTime() - offset).toISOString().slice(0, 10);
}
