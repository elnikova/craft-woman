/**
 * Temporary types for the home page until the catalog API exists.
 * When Resources appear (Maker, Product, Course, Workshop, Post),
 * replace these with mirrors of their toArray().
 */
export type Placeholder = { color: string; pattern: 'ph-knit' | 'ph-weave' | 'ph-stitch' };

export interface WorkshopRowData {
    id: number;
    title: string;
    makerName: string;
    startsAt: string; // ISO
    mode: 'online' | 'offline';
    place: string;
    capacity: number;
    booked: number;
    priceCents: number;
    currency: 'EUR' | 'USD' | 'UAH';
}

export interface PostCardData {
    id: number;
    makerName: string;
    text: string;
    likes: number;
    comments: number;
    cover: Placeholder;
}
