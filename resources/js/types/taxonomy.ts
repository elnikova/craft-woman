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
}
