import { http } from './http';
import type { Product, ProductFilters, ProductPayload, ProductStatus } from '@/types/product';
import type { Paginated, Wrapped } from '@/types/user';

const unwrap = <T>(p: Promise<{ data: Wrapped<T> }>) => p.then((r) => r.data.data);

export const productsApi = {
    async list(filters: ProductFilters = {}): Promise<Paginated<Product>> {
        const { data } = await http.get<Paginated<Product>>('/products', { params: filters });
        return data;
    },

    show: (slug: string) => unwrap<Product>(http.get(`/products/${encodeURIComponent(slug)}`)),

    // ---- maker account ----
    async mine(status?: ProductStatus): Promise<Paginated<Product>> {
        const { data } = await http.get<Paginated<Product>>('/me/products', { params: status ? { status } : {} });
        return data;
    },
    mineOne: (uuid: string) => unwrap<Product>(http.get(`/me/products/${uuid}`)),
    create: (payload: ProductPayload) => unwrap<Product>(http.post('/me/products', payload)),
    update: (uuid: string, payload: ProductPayload) => unwrap<Product>(http.put(`/me/products/${uuid}`, payload)),
    remove: (uuid: string) => http.delete(`/me/products/${uuid}`),
    submit: (uuid: string) => unwrap<Product>(http.post(`/me/products/${uuid}/submit`)),
    archive: (uuid: string) => unwrap<Product>(http.post(`/me/products/${uuid}/archive`)),
    relist: (uuid: string) => unwrap<Product>(http.post(`/me/products/${uuid}/relist`)),

    uploadImage(uuid: string, file: File, onProgress?: (pct: number) => void) {
        const form = new FormData();
        form.append('image', file);
        return unwrap<Product>(
            http.post(`/me/products/${uuid}/images`, form, {
                onUploadProgress: (e) => onProgress?.(e.total ? Math.round((e.loaded / e.total) * 100) : 0),
            }),
        );
    },
    deleteImage: (uuid: string, imageUuid: string) => unwrap<Product>(http.delete(`/me/products/${uuid}/images/${imageUuid}`)),
    reorderImages: (uuid: string, order: string[]) => unwrap<Product>(http.put(`/me/products/${uuid}/images/order`, { order })),

    uploadFile(uuid: string, file: File, onProgress?: (pct: number) => void) {
        const form = new FormData();
        form.append('file', file);
        return unwrap<Product>(
            http.post(`/me/products/${uuid}/files`, form, {
                onUploadProgress: (e) => onProgress?.(e.total ? Math.round((e.loaded / e.total) * 100) : 0),
            }),
        );
    },
    deleteFile: (uuid: string, fileUuid: string) => unwrap<Product>(http.delete(`/me/products/${uuid}/files/${fileUuid}`)),

    // ---- moderation ----
    async reviewQueue(status: ProductStatus): Promise<Paginated<Product>> {
        const { data } = await http.get<Paginated<Product>>('/admin/products', { params: { status } });
        return data;
    },
    review: (uuid: string, action: 'approve' | 'reject' | 'archive', note?: string) =>
        unwrap<Product>(http.post(`/admin/products/${uuid}/${action}`, note ? { note } : {})),
};
