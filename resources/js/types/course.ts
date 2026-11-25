import type { Currency, Money } from './product';
import type { MakerBrief } from './order';

/** Mirrors Modules\Education\Domain\CourseStatus (same values as products) */
export type CourseStatus = 'draft' | 'pending_review' | 'published' | 'rejected' | 'archived';
export type AccessType = 'lifetime' | 'limited';
export type MaterialType = 'text' | 'video' | 'file' | 'link';

/** CoursePresenter::card */
export interface CourseCard {
    uuid: string;
    slug: string;
    name: string;
    description: string | null;
    content_locale: 'uk' | 'en';
    cover_url: string | null;
    price: Money;
    access_type: AccessType;
    access_days: number | null;
    certificate_enabled: boolean;
    lessons_count: number;
    modules_count: number;
    category: { uuid: string; name: string; slug: string } | null;
    maker: MakerBrief | null;
    published_at: string | null;
    /** Maker's own list only */
    status?: CourseStatus;
    review_note?: string | null;
}

export interface OutlineLesson {
    uuid: string;
    title: string;
    is_preview: boolean;
    is_required: boolean;
    material_types: MaterialType[];
}

export interface CourseProgress {
    completed: number;
    total: number;
    required_completed: number;
    required_total: number;
    percent: number;
    completed_lessons: string[];
}

export interface CourseEnrollment extends CourseProgress {
    has_access: boolean;
    expires_at: string | null;
    completed_at: string | null;
    certificate_number: string | null;
}

/** Public course page */
export interface CourseDetail extends CourseCard {
    status: CourseStatus;
    is_owner: boolean;
    modules: { uuid: string; title: string; description: string | null; lessons: OutlineLesson[] }[];
    enrollment: CourseEnrollment | null;
}

export interface Material {
    uuid: string;
    type: MaterialType;
    title: string | null;
    content: string | null;
    url: string | null;
    embed_url: string | null;
    provider: 'youtube' | 'vimeo' | null;
    file: { name: string; size: number } | null;
}

export interface LessonView {
    uuid: string;
    title: string;
    description: string | null;
    is_required: boolean;
    is_preview: boolean;
    module: { uuid: string; title: string };
    materials: Material[];
    completed: boolean;
    previous: { uuid: string; title: string } | null;
    next: { uuid: string; title: string } | null;
}

export interface EditorLesson {
    uuid: string;
    title: string;
    description: string | null;
    is_required: boolean;
    is_preview: boolean;
    materials: Material[];
}

/** Maker editor and admin review (CoursePresenter::editor) */
export interface CourseEditor extends CourseCard {
    status: CourseStatus;
    review_note: string | null;
    is_editable: boolean;
    is_deletable: boolean;
    missing: string[];
    students_count: number;
    translations: Partial<Record<'uk' | 'en', { name?: string; description?: string }>>;
    modules: { uuid: string; title: string; description: string | null; lessons: EditorLesson[] }[];
}

export interface CoursePayload {
    name: string;
    description: string | null;
    price_amount_minor: number;
    currency: Currency;
    access_type: AccessType;
    access_days: number | null;
    certificate_enabled: boolean;
    category_uuid: string | null;
    content_locale: 'uk' | 'en';
    translations: Partial<Record<'uk' | 'en', { name: string | null; description: string | null }>>;
}

export interface LearningItem extends CourseProgress {
    course: CourseCard;
    has_access: boolean;
    expires_at: string | null;
    completed_at: string | null;
    certificate_number: string | null;
}

export interface CertificateView {
    number: string;
    holder_name: string;
    course_name: string;
    maker_name: string;
    issued_at: string;
    course_slug: string | null;
}
