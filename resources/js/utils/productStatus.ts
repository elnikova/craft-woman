import { labelMap } from '@/i18n';
import type { ProductStatus } from '@/types/product';

export const PRODUCT_STATUS_LABELS = labelMap<ProductStatus>('status.product', ['draft', 'pending_review', 'published', 'rejected', 'archived']);

export const PRODUCT_STATUS_TONE: Record<ProductStatus, string> = {
    draft: 'bg-lavender-50 text-plum',
    pending_review: 'bg-notice-bg text-notice-ink',
    published: 'bg-[#E3EDD8] text-[#2F4A1C]',
    rejected: 'bg-kalyna/10 text-kalyna',
    archived: 'bg-ink/10 text-ink',
};
