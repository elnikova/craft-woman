import type { GroupStatus, OrderStatus, PaymentMethod } from '@/types/order';

export const ORDER_STATUS_LABELS: Record<OrderStatus, string> = {
    pending_payment: 'Очікує оплати',
    processing: 'Готується',
    partially_shipped: 'Частково відправлено',
    shipped: 'Відправлено',
    completed: 'Виконано',
    cancelled: 'Скасовано',
};

export const GROUP_STATUS_LABELS: Record<GroupStatus, string> = {
    pending_payment: 'Очікує оплати',
    awaiting_shipment: 'Готується до відправки',
    shipped: 'Відправлено',
    delivered: 'Доставлено',
    completed: 'Виконано',
    cancelled: 'Скасовано',
};

export const PAYMENT_LABELS: Record<PaymentMethod, string> = {
    online: 'Онлайн карткою',
    cash_on_delivery: 'При отриманні',
};

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

export const COUNTRIES = [
    { code: 'UA', name: 'Україна' },
    { code: 'PL', name: 'Польща' },
    { code: 'DE', name: 'Німеччина' },
    { code: 'BG', name: 'Болгарія' },
    { code: 'CZ', name: 'Чехія' },
    { code: 'RO', name: 'Румунія' },
    { code: 'MD', name: 'Молдова' },
    { code: 'SK', name: 'Словаччина' },
    { code: 'LT', name: 'Литва' },
    { code: 'IT', name: 'Італія' },
    { code: 'FR', name: 'Франція' },
    { code: 'ES', name: 'Іспанія' },
    { code: 'NL', name: 'Нідерланди' },
    { code: 'AT', name: 'Австрія' },
    { code: 'GB', name: 'Велика Британія' },
    { code: 'US', name: 'США' },
    { code: 'CA', name: 'Канада' },
];

export function formatDate(iso: string | null): string {
    if (!iso) return '';
    return new Intl.DateTimeFormat('uk-UA', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(iso));
}
