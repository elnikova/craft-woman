/**
 * Тимчасові типи для головної, поки немає API каталогу.
 * Коли з'являться Resources (Maker, Product, Course, Workshop, Post),
 * замінити на дзеркала їхніх toArray().
 */
export type Placeholder = { color: string; pattern: 'ph-knit' | 'ph-weave' | 'ph-stitch' };

export interface CourseCardData {
    id: number;
    title: string;
    makerName: string;
    lessonsCount: number;
    /** null — доступ назавжди, інакше кількість місяців */
    accessMonths: number | null;
    hasCertificate: boolean;
    priceCents: number;
    currency: 'EUR' | 'USD' | 'UAH';
    cover: Placeholder;
}

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
