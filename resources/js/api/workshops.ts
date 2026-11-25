import { http } from './http';
import type { Booking, Participant, WorkshopCard, WorkshopDetail, WorkshopEditor, WorkshopFormat, WorkshopPayload, WorkshopStatus } from '@/types/workshop';
import type { Paginated, Wrapped } from '@/types/user';

const unwrap = <T>(p: Promise<{ data: Wrapped<T> }>) => p.then((r) => r.data.data);

export const workshopsApi = {
    async list(params: { q?: string; format?: WorkshopFormat; city?: string; maker?: string; page?: number; per_page?: number } = {}): Promise<Paginated<WorkshopCard>> {
        const { data } = await http.get<Paginated<WorkshopCard>>('/workshops', { params });
        return data;
    },
    show: (slug: string) => unwrap<WorkshopDetail>(http.get(`/workshops/${slug}`)),
    bookings: () => unwrap<Booking[]>(http.get('/me/bookings')),

    // ---- maker ----
    async mine(status?: WorkshopStatus): Promise<Paginated<WorkshopCard>> {
        const { data } = await http.get<Paginated<WorkshopCard>>('/me/workshops', { params: status ? { status } : {} });
        return data;
    },
    mineOne: (uuid: string) => unwrap<WorkshopEditor>(http.get(`/me/workshops/${uuid}`)),
    create: (payload: WorkshopPayload) => unwrap<WorkshopEditor>(http.post('/me/workshops', payload)),
    update: (uuid: string, payload: Partial<WorkshopPayload>) => unwrap<WorkshopEditor>(http.put(`/me/workshops/${uuid}`, payload)),
    remove: (uuid: string) => http.delete(`/me/workshops/${uuid}`),
    cover(uuid: string, file: File) {
        const form = new FormData();
        form.append('cover', file);
        return unwrap<WorkshopEditor>(http.post(`/me/workshops/${uuid}/cover`, form));
    },
    submit: (uuid: string) => unwrap<WorkshopEditor>(http.post(`/me/workshops/${uuid}/submit`)),
    archive: (uuid: string) => unwrap<WorkshopEditor>(http.post(`/me/workshops/${uuid}/archive`)),
    relist: (uuid: string) => unwrap<WorkshopEditor>(http.post(`/me/workshops/${uuid}/relist`)),
    cancel: (uuid: string, reason: string) => unwrap<WorkshopEditor>(http.post(`/me/workshops/${uuid}/cancel`, { reason })),
    addSession: (uuid: string, starts_at: string, ends_at: string) => unwrap<WorkshopEditor>(http.post(`/me/workshops/${uuid}/sessions`, { starts_at, ends_at })),
    updateSession: (session: string, starts_at: string, ends_at: string) => unwrap<WorkshopEditor>(http.put(`/me/workshop-sessions/${session}`, { starts_at, ends_at })),
    deleteSession: (session: string) => unwrap<WorkshopEditor>(http.delete(`/me/workshop-sessions/${session}`)),
    async participants(uuid: string): Promise<{ data: Participant[]; meta: { seats_confirmed: number; seats_pending: number; capacity: number } }> {
        const { data } = await http.get(`/me/workshops/${uuid}/participants`);
        return data;
    },

    // ---- admin ----
    async review(status: WorkshopStatus): Promise<Paginated<WorkshopEditor>> {
        const { data } = await http.get<Paginated<WorkshopEditor>>('/admin/workshops', { params: { status } });
        return data;
    },
    approve: (uuid: string) => unwrap<WorkshopEditor>(http.post(`/admin/workshops/${uuid}/approve`)),
    reject: (uuid: string, note: string) => unwrap<WorkshopEditor>(http.post(`/admin/workshops/${uuid}/reject`, { note })),
    archiveAsAdmin: (uuid: string, note: string) => unwrap<WorkshopEditor>(http.post(`/admin/workshops/${uuid}/archive`, { note })),
};
