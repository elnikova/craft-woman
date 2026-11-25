import { INTL_LOCALE, currentLocale, labelMap } from '@/i18n';
import type { GroupStatus, OrderStatus, PaymentMethod } from '@/types/order';

export const ORDER_STATUS_LABELS = labelMap<OrderStatus>('status.order', ['pending_payment', 'processing', 'partially_shipped', 'shipped', 'completed', 'cancelled']);

export const GROUP_STATUS_LABELS = labelMap<GroupStatus>('status.group', ['pending_payment', 'awaiting_shipment', 'shipped', 'delivered', 'completed', 'cancelled']);

export const PAYMENT_LABELS = labelMap<PaymentMethod>('status.payment', ['online', 'cash_on_delivery']);

type Tone = 'wait' | 'work' | 'done' | 'off';
const TONES: Record<Tone, string> = {
    wait: 'bg-notice-bg text-notice-ink',
    work: 'bg-lavender-100 text-plum',
    done: 'bg-[#E6F0DC] text-[#2F4A1C]',
    off: 'bg-[#EFEDF2] text-ink-muted',
};

export function statusTone(status: OrderStatus | GroupStatus): string {
    const tone: Tone =
        status === 'pending_payment'
            ? 'wait'
            : status === 'cancelled'
              ? 'off'
              : status === 'completed' || status === 'delivered'
                ? 'done'
                : 'work';
    return TONES[tone];
}

/** Delivery countries; names come from Intl in the interface language */
const COUNTRY_CODES = ['UA', 'PL', 'DE', 'BG', 'CZ', 'RO', 'MD', 'SK', 'LT', 'IT', 'FR', 'ES', 'NL', 'AT', 'GB', 'US', 'CA'] as const;

export function countryName(code: string): string {
    try {
        return new Intl.DisplayNames([INTL_LOCALE[currentLocale()]], { type: 'region' }).of(code) ?? code;
    } catch {
        return code;
    }
}

/** Ukraine first, the rest alphabetically in the current language */
export function countries(): { code: string; name: string }[] {
    const [ua, ...rest] = COUNTRY_CODES.map((code) => ({ code, name: countryName(code) }));
    return [ua!, ...rest.sort((a, b) => a.name.localeCompare(b.name, INTL_LOCALE[currentLocale()]))];
}

export function formatDate(iso: string | null): string {
    if (!iso) return '';
    return new Intl.DateTimeFormat(INTL_LOCALE[currentLocale()], { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(iso));
}
