import { http } from './http';
import type { Product } from '@/types/product';
import type { Paginated } from '@/types/user';

export const favoritesApi = {
    ids: () => http.get<{ data: string[] }>('/me/favorites/ids').then((r) => r.data.data),
    async list(page = 1): Promise<Paginated<Product>> {
        const { data } = await http.get<Paginated<Product>>('/me/favorites', { params: { page } });
        return data;
    },
    add: (product: string) => http.put(`/me/favorites/${product}`),
    remove: (product: string) => http.delete(`/me/favorites/${product}`),
};
