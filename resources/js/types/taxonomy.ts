/** Mirrors CategoryResource */
export interface Category {
    uuid: string;
    parent_uuid: string | null;
    name: string;
    slug: string;
    description: string | null;
    sort_order: number;
    is_visible: boolean;
    children?: Category[];
    /** Admin only: source text and translations, for editing */
    source?: { locale: 'uk' | 'en'; name: string; description: string | null };
    translations?: Partial<Record<'uk' | 'en', { name?: string; description?: string }>>;
    /** Admin only: how many products/courses are attached */
    usage_count?: number;
}

export interface Tag {
    uuid: string;
    name: string;
    slug: string;
}

export interface CategoryPayload {
    name?: string;
    parent_uuid?: string | null;
    description?: string | null;
    sort_order?: number;
    is_visible?: boolean;
    content_locale?: 'uk' | 'en';
    translations?: Partial<Record<'uk' | 'en', { name?: string | null; description?: string | null }>>;
}
