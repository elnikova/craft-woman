import type { Currency } from './product';

/** Дзеркало Modules\Identity\Domain\MakerStatus */
export type MakerStatus = 'draft' | 'pending_review' | 'approved' | 'rejected' | 'suspended';

/** Дзеркало MakerProfileResource. Приватні поля приходять тільки власнику й адміністратору. */
export interface MakerProfile {
    uuid: string;
    slug: string;
    display_name: string;
    bio: string | null;
    story: string | null;
    avatar_url: string | null;
    country_code: string | null;
    city: string | null;
    follow_enabled: boolean;
    approved_at: string | null;
    // приватні
    address?: string | null;
    status?: MakerStatus;
    review_note?: string | null;
    is_editable?: boolean;
    checkout?: MakerCheckout;
    created_at?: string;
    updated_at?: string;
    // тільки для адміністратора
    owner?: { uuid: string; name: string; email: string };
}

export interface MakerProfilePayload {
    display_name: string;
    bio: string | null;
    story: string | null;
    avatar_url: string | null;
    country_code: string | null;
    city: string | null;
    address: string | null;
    follow_enabled: boolean;
    cod_enabled?: boolean;
    delivery_currency?: Currency;
    delivery_domestic_minor?: number;
    /** null — майстер не відправляє за кордон */
    delivery_international_minor?: number | null;
}

/** Налаштування доставки й оплати майстра (бачить лише власник) */
export interface MakerCheckout {
    cod_enabled: boolean;
    delivery_currency: Currency;
    delivery_domestic_minor: number;
    delivery_international_minor: number | null;
}
