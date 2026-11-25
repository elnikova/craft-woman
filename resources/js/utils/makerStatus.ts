import { labelMap } from '@/i18n';
import type { MakerStatus } from '@/types/maker';

export const MAKER_STATUS_LABELS = labelMap<MakerStatus>('status.maker', ['draft', 'pending_review', 'approved', 'rejected', 'suspended']);
