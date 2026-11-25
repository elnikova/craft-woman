import { INTL_LOCALE, currentLocale, t } from '@/i18n';

/** Intl tag of the interface language, read on every call so formatting follows the switcher */
const intl = () => INTL_LOCALE[currentLocale()];

export function formatPrice(cents: number, currency: string): string {
    return new Intl.NumberFormat(intl(), {
        style: 'currency',
        currency,
        maximumFractionDigits: cents % 100 === 0 ? 0 : 2,
    }).format(cents / 100);
}

export function formatRating(value: number): string {
    return new Intl.NumberFormat(intl(), { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(value);
}

/** Count with the right plural form: plural(3, 'lessons') → "3 уроки" / "3 lessons" (messages under `plural.*`) */
export function plural(n: number, key: string): string {
    return t(`plural.${key}`, { n }, n);
}

export function formatDay(iso: string): { day: string; month: string; time: string } {
    const d = new Date(iso);
    return {
        day: new Intl.DateTimeFormat(intl(), { day: 'numeric' }).format(d),
        month: new Intl.DateTimeFormat(intl(), { month: 'short' }).format(d).replace('.', ''),
        time: new Intl.DateTimeFormat(intl(), { hour: '2-digit', minute: '2-digit' }).format(d),
    };
}

/** Minor units → input string: 2800 → "28", 2850 → "28.50" */
export function minorToInput(minor: number | null | undefined): string {
    if (minor === null || minor === undefined) return '';
    return minor % 100 === 0 ? String(minor / 100) : (minor / 100).toFixed(2);
}

/** Input string → minor units without float errors: "28,5" → 2850 */
export function inputToMinor(value: string): number | null {
    const v = value.trim().replace(',', '.').replace(/\s/g, '');
    if (!v) return null;
    if (!/^\d+(\.\d{0,2})?$/.test(v)) return NaN;
    const [whole, frac = ''] = v.split('.');
    return Number(whole) * 100 + Number(frac.padEnd(2, '0'));
}

export function formatFileSize(bytes: number): string {
    if (bytes < 1024) return t('units.bytes', { n: bytes });
    if (bytes < 1024 * 1024) return t('units.kb', { n: Math.round(bytes / 1024) });
    return t('units.mb', { n: (bytes / 1024 / 1024).toFixed(1) });
}
