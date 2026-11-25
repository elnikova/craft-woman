export type ProductType = 'physical' | 'digital';
export type ProductStatus = 'draft' | 'pending_review' | 'published' | 'rejected' | 'archived';
export type Currency = 'EUR' | 'USD' | 'UAH';

export interface Money {
    amount_minor: number;
    currency: Currency;
}

export interface ProductVariant {
    uuid: string;
    name: string;
    price: Money;
    in_stock: boolean;
    /** Public: only when stock is low (≤3); always in the account area */
    stock: number | null;
    is_default: boolean;
    // maker account
    sku?: string | null;
    price_override_minor?: number | null;
    weight_grams?: number | null;
    status?: 'active' | 'inactive';
}

export interface ProductImage {
    uuid: string;
    url: string;
    alt: string;
}

export interface ProductFile {
    uuid: string;
    name: string;
    size: number;
    mime_type: string | null;
}

/** Mirrors ProductResource */
export interface Product {
    uuid: string;
    slug: string;
    type: ProductType;
    is_digital: boolean;
    name: string;
    description: string | null;
    price: Money;
    production_time_days: number | null;
    shipping_info: string | null;
    published_at: string | null;
    category: { uuid: string; name: string; slug: string } | null;
    tags: string[];
    images: ProductImage[];
    variants: ProductVariant[];
    maker: { uuid: string; display_name: string; slug: string; city: string | null; avatar_url: string | null } | null;
    // maker account / admin
    status?: ProductStatus;
    review_note?: string | null;
    is_editable?: boolean;
    is_deletable?: boolean;
    files?: ProductFile[];
    created_at?: string;
    updated_at?: string;
}

export interface VariantPayload {
    uuid?: string | null;
    name: string;
    sku: string | null;
    price_amount_minor: number | null;
    stock: number;
    weight_grams: number | null;
    status: 'active' | 'inactive';
}

export interface ProductPayload {
    type: ProductType;
    name: string;
    description: string | null;
    price_amount_minor: number;
    currency: Currency;
    production_time_days: number | null;
    shipping_info: string | null;
    category_uuid: string | null;
    tags: string[];
    variants?: VariantPayload[];
}

export interface ProductFilters {
    category?: string;
    maker?: string;
    type?: ProductType;
    q?: string;
    sort?: 'newest' | 'price_asc' | 'price_desc';
    page?: number;
    per_page?: number;
}
