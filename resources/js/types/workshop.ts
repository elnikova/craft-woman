import type { Currency, Money } from './product';
import type { MakerBrief } from './order';

/** Mirrors Modules\Workshops\Domain\WorkshopStatus */
export type WorkshopStatus = 'draft' | 'pending_review' | 'published' | 'rejected' | 'archived' | 'cancelled';
export type WorkshopFormat = 'online' | 'offline';

export interface WorkshopSession {
    uuid: string;
    starts_at: string;
    ends_at: string;
    /** Wall-clock time in the workshop's timezone, "YYYY-MM-DDTHH:mm" */
    starts_local: string;
    ends_local: string;
}

/** WorkshopPresenter::card */
export interface WorkshopCard {
    uuid: string;
    slug: string;
    name: string;
    description: string | null;
    content_locale: 'uk' | 'en';
    cover_url: string | null;
    price: Money;
    format: WorkshopFormat;
    timezone: string;
    city: string | null;
    country_code: string | null;
    capacity: number;
    seats_left: number;
    starts_at: string | null;
    ends_at: string | null;
    sessions_count: number;
    is_bookable: boolean;
    has_ended: boolean;
    category: { uuid: string; name: string; slug: string } | null;
    maker: MakerBrief | null;
    published_at: string | null;
    /** Maker's own list only */
    status?: WorkshopStatus;
    review_note?: string | null;
    seats_booked?: number;
}

/** Only for participants, the owner and admins */
export interface WorkshopPrivate {
    address: string | null;
    meeting_url: string | null;
    note: string | null;
}

export interface WorkshopDetail extends WorkshopCard {
    status: WorkshopStatus;
    cancel_reason: string | null;
    sessions: WorkshopSession[];
    private: WorkshopPrivate | null;
    is_owner: boolean;
    my_seats: number;
}

export interface WorkshopEditor extends Omit<WorkshopDetail, 'is_owner' | 'my_seats'> {
    participant_note: string | null;
    translations: Partial<Record<'uk' | 'en', { name?: string; description?: string; participant_note?: string }>>;
    review_note: string | null;
    is_editable: boolean;
    is_deletable: boolean;
    missing: string[];
    seats_booked: number;
    location_address: string | null;
    external_url: string | null;
}

export interface WorkshopPayload {
    name: string;
    description: string | null;
    price_amount_minor: number;
    currency: Currency;
    format: WorkshopFormat;
    timezone: string;
    capacity: number;
    location_country_code: string | null;
    location_city: string | null;
    location_address: string | null;
    external_url: string | null;
    participant_note: string | null;
    category_uuid: string | null;
    content_locale: 'uk' | 'en';
    translations: Partial<Record<'uk' | 'en', { name: string | null; description: string | null; participant_note: string | null }>>;
}

export interface Booking {
    uuid: string;
    seats: number;
    status: 'confirmed' | 'cancelled';
    booked_at: string;
    cancelled_at: string | null;
    cancel_reason: string | null;
    workshop: WorkshopCard & { sessions: WorkshopSession[]; private: WorkshopPrivate | null };
}

export interface Participant {
    uuid: string;
    name: string | null;
    email: string | null;
    seats: number;
    confirmed_at: string;
}
