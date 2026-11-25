const locale = 'uk-UA';

export function formatPrice(cents: number, currency: string): string {
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
        maximumFractionDigits: cents % 100 === 0 ? 0 : 2,
    }).format(cents / 100);
}

export function formatRating(value: number): string {
    return new Intl.NumberFormat(locale, { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(value);
}

/** Українські форми множини: plural(3, ['урок', 'уроки', 'уроків']) → «3 уроки» */
export function plural(n: number, forms: [string, string, string]): string {
    const rule = new Intl.PluralRules(locale).select(n);
    const word = rule === 'one' ? forms[0] : rule === 'few' ? forms[1] : forms[2];
    return `${n} ${word}`;
}

export function formatDay(iso: string): { day: string; month: string; time: string } {
    const d = new Date(iso);
    return {
        day: new Intl.DateTimeFormat(locale, { day: 'numeric' }).format(d),
        month: new Intl.DateTimeFormat(locale, { month: 'short' }).format(d).replace('.', ''),
        time: new Intl.DateTimeFormat(locale, { hour: '2-digit', minute: '2-digit' }).format(d),
    };
}

/** Мінорні одиниці → рядок для поля вводу: 2800 → "28", 2850 → "28.50" */
export function minorToInput(minor: number | null | undefined): string {
    if (minor === null || minor === undefined) return '';
    return minor % 100 === 0 ? String(minor / 100) : (minor / 100).toFixed(2);
}

/** Рядок з поля вводу → мінорні одиниці без float-помилок: "28,5" → 2850 */
export function inputToMinor(value: string): number | null {
    const v = value.trim().replace(',', '.').replace(/\s/g, '');
    if (!v) return null;
    if (!/^\d+(\.\d{0,2})?$/.test(v)) return NaN;
    const [whole, frac = ''] = v.split('.');
    return Number(whole) * 100 + Number(frac.padEnd(2, '0'));
}

export function formatFileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} Б`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} КБ`;
    return `${(bytes / 1024 / 1024).toFixed(1)} МБ`;
}
