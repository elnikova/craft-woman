import type { MakerStatus } from '@/types/maker';

export const MAKER_STATUS_LABELS: Record<MakerStatus, string> = {
    draft: 'Чернетка',
    pending_review: 'На перевірці',
    approved: 'Опубліковано',
    rejected: 'Потрібні правки',
    suspended: 'Призупинено',
};
