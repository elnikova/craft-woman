import { t } from '@/i18n';
import type { CourseCard } from '@/types/course';

/** "Lifetime access" / "90 days access" */
export function accessLabel(c: Pick<CourseCard, 'access_type' | 'access_days'>): string {
    if (c.access_type === 'lifetime' || !c.access_days) return t('courses.lifetime');
    return t('courses.days', { days: t('courses.daysPlural', { n: c.access_days }, c.access_days) });
}
