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
}
