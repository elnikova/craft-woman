import type { Ref } from 'vue';
import { createI18n } from 'vue-i18n';
import en from './en.json';
import uk from './uk.json';

export const LOCALES = ['uk', 'en'] as const;
export type Locale = (typeof LOCALES)[number];
export const DEFAULT_LOCALE: Locale = 'uk';

/** Intl tags for number, date and region formatting */
export const INTL_LOCALE: Record<Locale, string> = { uk: 'uk-UA', en: 'en-GB' };

export type MessageSchema = typeof uk;

/**
 * Ukrainian has three plural forms: one (1, 21), few (2–4, 22–24) and many (0, 5–20, 11–14).
 * Messages list them as "товар | товари | товарів".
 */
function ukrainianPlural(choice: number, choicesLength: number): number {
    if (choicesLength < 3) return choice === 1 ? 0 : 1;
    const rule = new Intl.PluralRules('uk-UA').select(choice);
    return rule === 'one' ? 0 : rule === 'few' ? 1 : 2;
}

export const i18n = createI18n({
    legacy: false,
    locale: DEFAULT_LOCALE,
    fallbackLocale: DEFAULT_LOCALE,
    messages: { uk, en },
    pluralRules: { uk: ukrainianPlural },
    missingWarn: import.meta.env.DEV,
    fallbackWarn: false,
});

export const t = i18n.global.t;

/** Composition-mode global locale (typed loosely by vue-i18n) */
const globalLocale = i18n.global.locale as unknown as Ref<Locale>;

export function currentLocale(): Locale {
    return globalLocale.value;
}

export function isLocale(value: unknown): value is Locale {
    return typeof value === 'string' && (LOCALES as readonly string[]).includes(value);
}

/** Switch the interface language: messages, <html lang> and the stored choice. The API client reads it per request. */
export function applyLocale(locale: Locale): void {
    globalLocale.value = locale;
    document.documentElement.lang = locale;
    try {
        localStorage.setItem('cw.locale', locale);
    } catch {
        /* private mode: the URL still carries the language */
    }
}

/** Path for the current language: Ukrainian has no prefix, English lives under /en. */
export function localePath(path: string, locale: Locale = currentLocale()): string {
    const clean = path.replace(/^\/en(?=\/|$)/, '') || '/';
    if (locale === DEFAULT_LOCALE) return clean;
    return clean === '/' ? '/en' : `/en${clean}`;
}

/**
 * Label maps whose values follow the current language, so existing `LABELS[key]`
 * lookups stay reactive in templates: `labelMap('order.status', ['paid', …])`.
 */
export function labelMap<K extends string>(prefix: string, keys: readonly K[]): Record<K, string> {
    const map = {} as Record<K, string>;
    for (const key of keys) {
        Object.defineProperty(map, key, { enumerable: true, get: () => t(`${prefix}.${key}`) });
    }
    return map;
}
