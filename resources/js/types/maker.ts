import type { Currency } from './product';

/** Mirrors Modules\Identity\Domain\MakerStatus */
export type MakerStatus = 'draft' | 'pending_review' | 'approved' | 'rejected' | 'suspended';

/** Mirrors MakerProfileResource. Private fields are sent only to the owner and administrators. */
export interface MakerProfile {
    uuid: string;
    slug: string;
    display_name: string;
    bio: string | null;
    story: string | null;
    /** Language of bio/story: the source language when no translation exists */
    content_locale?: 'uk' | 'en';
    /** Owner/admin only: translations keyed by locale */
    translations?: Partial<Record<'uk' | 'en', { bio?: string; story?: string }>>;
    avatar_url: string | null;
    country_code: string | null;
    city: string | null;
    follow_enabled: boolean;
    approved_at: string | null;
    // private
    address?: string | null;
    status?: MakerStatus;
    review_note?: string | null;
    is_editable?: boolean;
    checkout?: MakerCheckout;
    created_at?: string;
    updated_at?: string;
    // administrators only
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
    /** null means the maker does not ship abroad */
    delivery_international_minor?: number | null;
    content_locale?: 'uk' | 'en';
    translations?: Partial<Record<'uk' | 'en', { bio?: string | null; story?: string | null }>>;
}

/** Maker delivery and payment settings (visible to the owner only) */
export interface MakerCheckout {
    cod_enabled: boolean;
    delivery_currency: Currency;
    delivery_domestic_minor: number;
    delivery_international_minor: number | null;
}
