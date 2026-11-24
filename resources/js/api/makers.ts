import { http } from './http';
import type { MakerProfile, MakerProfilePayload, MakerStatus } from '@/types/maker';
import type { Paginated, Wrapped } from '@/types/user';

export const makersApi = {
    async list(params: { page?: number; per_page?: number; q?: string; city?: string } = {}): Promise<Paginated<MakerProfile>> {
        const { data } = await http.get<Paginated<MakerProfile>>('/makers', { params });
        return data;
    },

    async show(slug: string): Promise<MakerProfile> {
        const { data } = await http.get<Wrapped<MakerProfile>>(`/makers/${encodeURIComponent(slug)}`);
        return data.data;
    },

    // ---- власний профіль ----
    async mine(): Promise<MakerProfile | null> {
        const { data } = await http.get<Wrapped<MakerProfile | null>>('/me/maker-profile');
        return data.data;
    },

    async saveMine(payload: MakerProfilePayload): Promise<MakerProfile> {
        const { data } = await http.put<Wrapped<MakerProfile>>('/me/maker-profile', payload);
        return data.data;
    },

    async submitMine(): Promise<MakerProfile> {
        const { data } = await http.post<Wrapped<MakerProfile>>('/me/maker-profile/submit');
        return data.data;
    },

    // ---- модерація ----
    async reviewQueue(status: MakerStatus, page = 1): Promise<Paginated<MakerProfile>> {
        const { data } = await http.get<Paginated<MakerProfile>>('/admin/makers', { params: { status, page } });
        return data;
    },

    /** reject і suspend вимагають note — бекенд поверне 422 без нього */
    async review(uuid: string, action: 'approve' | 'reinstate' | 'reject' | 'suspend', note?: string): Promise<MakerProfile> {
        const { data } = await http.post<Wrapped<MakerProfile>>(`/admin/makers/${uuid}/${action}`, note ? { note } : {});
        return data.data;
    },
};
