import { http } from './http';
import type { Category, CategoryPayload, Tag } from '@/types/taxonomy';
import type { Wrapped } from '@/types/user';

export const taxonomyApi = {
    async categories(): Promise<Category[]> {
        const { data } = await http.get<Wrapped<Category[]>>('/categories');
        return data.data;
    },

    async tags(q = ''): Promise<Tag[]> {
        const { data } = await http.get<Wrapped<Tag[]>>('/tags', { params: q ? { q } : {} });
        return data.data;
    },

    // ---- адміністрування ----
    async adminCategories(): Promise<Category[]> {
        const { data } = await http.get<Wrapped<Category[]>>('/admin/categories');
        return data.data;
    },

    async createCategory(payload: CategoryPayload): Promise<Category> {
        const { data } = await http.post<Wrapped<Category>>('/admin/categories', payload);
        return data.data;
    },

    async updateCategory(uuid: string, payload: CategoryPayload): Promise<Category> {
        const { data } = await http.put<Wrapped<Category>>(`/admin/categories/${uuid}`, payload);
        return data.data;
    },

    async deleteCategory(uuid: string): Promise<void> {
        await http.delete(`/admin/categories/${uuid}`);
    },
};
