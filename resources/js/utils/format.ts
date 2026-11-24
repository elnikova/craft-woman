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
