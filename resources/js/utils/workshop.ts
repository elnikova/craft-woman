import { INTL_LOCALE, currentLocale, labelMap } from '@/i18n';
import type { WorkshopPrivate, WorkshopSession, WorkshopStatus } from '@/types/workshop';
import { PRODUCT_STATUS_TONE } from './productStatus';

export const WORKSHOP_STATUS_LABELS = labelMap<WorkshopStatus>('status.workshop', ['draft', 'pending_review', 'published', 'rejected', 'archived', 'cancelled']);

export const WORKSHOP_STATUS_TONE: Record<WorkshopStatus, string> = { ...PRODUCT_STATUS_TONE, cancelled: 'bg-kalyna/10 text-kalyna' };

/** Common IANA zones offered in the editor; the browser's own zone is added when missing */
export const TIMEZONES = ['Europe/Kyiv', 'Europe/Warsaw', 'Europe/Berlin', 'Europe/Sofia', 'Europe/London', 'Europe/Lisbon', 'America/New_York', 'America/Toronto'];

const intl = () => INTL_LOCALE[currentLocale()];
const fmt = (iso: string, tz: string | undefined, opts: Intl.DateTimeFormatOptions) =>
    new Intl.DateTimeFormat(intl(), { ...opts, ...(tz ? { timeZone: tz } : {}) }).format(new Date(iso));

export const browserTimezone = () => Intl.DateTimeFormat().resolvedOptions().timeZone;

/** True when the visitor's clock shows a different time than the workshop's at that moment */
export function differsFromBrowser(iso: string, tz: string): boolean {
    const opts: Intl.DateTimeFormatOptions = { hour: '2-digit', minute: '2-digit', day: 'numeric' };
    return fmt(iso, tz, opts) !== fmt(iso, undefined, opts);
}

/** Big date block for rows: day number, short month, start time — in the workshop's zone */
export function dateBlock(iso: string, tz: string) {
    return {
        day: fmt(iso, tz, { day: 'numeric' }),
        month: fmt(iso, tz, { month: 'short' }).replace('.', ''),
        weekday: fmt(iso, tz, { weekday: 'short' }),
        time: fmt(iso, tz, { hour: '2-digit', minute: '2-digit' }),
    };
}

/** "пт, 12 грудня" */
export function dayLabel(iso: string, tz?: string): string {
    return fmt(iso, tz, { weekday: 'short', day: 'numeric', month: 'long' });
}

/** "пт, 12 грудня · 11:00–14:00" in the given zone (the visitor's when tz is undefined) */
export function sessionLabel(s: Pick<WorkshopSession, 'starts_at' | 'ends_at'>, tz?: string): string {
    const day = dayLabel(s.starts_at, tz);
    const from = fmt(s.starts_at, tz, { hour: '2-digit', minute: '2-digit' });
    const to = fmt(s.ends_at, tz, { hour: '2-digit', minute: '2-digit' });
    return `${day} · ${from}–${to}`;
}

/** Short zone name at that date, e.g. "GMT+2" / "EET" */
export function zoneName(iso: string, tz?: string): string {
    const parts = new Intl.DateTimeFormat(intl(), { timeZoneName: 'short', ...(tz ? { timeZone: tz } : {}) }).formatToParts(new Date(iso));
    return parts.find((p) => p.type === 'timeZoneName')?.value ?? tz ?? '';
}

/** Calendar file with one event per session (RFC 5545) */
export function icsFor(w: { uuid: string; name: string; city: string | null; private: WorkshopPrivate | null }, sessions: WorkshopSession[], pageUrl: string): string {
    const stamp = (iso: string) => new Date(iso).toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
    const esc = (s: string) => s.replace(/\\/g, '\\\\').replace(/\n/g, '\\n').replace(/([,;])/g, '\\$1');
    const location = w.private?.meeting_url ?? [w.private?.address, w.city].filter(Boolean).join(', ');
    const description = [w.private?.note, pageUrl].filter(Boolean).join('\n\n');
    const events = sessions.map((s) =>
        [
            'BEGIN:VEVENT',
            `UID:${s.uuid}@craftwoman`,
            `DTSTAMP:${stamp(new Date().toISOString())}`,
            `DTSTART:${stamp(s.starts_at)}`,
            `DTEND:${stamp(s.ends_at)}`,
            `SUMMARY:${esc(w.name)}`,
            location ? `LOCATION:${esc(location)}` : '',
            `DESCRIPTION:${esc(description)}`,
            'END:VEVENT',
        ]
            .filter(Boolean)
            .join('\r\n'),
    );
    return ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Craft Woman//Workshops//EN', ...events, 'END:VCALENDAR'].join('\r\n');
}

export function downloadIcs(filename: string, content: string): void {
    const url = URL.createObjectURL(new Blob([content], { type: 'text/calendar;charset=utf-8' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}
