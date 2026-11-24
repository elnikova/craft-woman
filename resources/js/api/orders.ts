import { http } from './http';
import type { Cart, DeliveryAddress, DownloadFile, GroupStatus, Order, OrderStatus, PaymentMethod, Quote, Sale } from '@/types/order';
import type { Paginated, Wrapped } from '@/types/user';

const unwrap = <T>(p: Promise<{ data: Wrapped<T> }>) => p.then((r) => r.data.data);

/** Гостьовий кошик живе за UUID з localStorage; сервер приймає його в заголовку */
const TOKEN_KEY = 'cw.cart';
export const cartToken = {
    get: (): string | null => {
        try {
            return localStorage.getItem(TOKEN_KEY);
        } catch {
            return null;
        }
    },
    set(token: string | null) {
        try {
            if (token) localStorage.setItem(TOKEN_KEY, token);
            else localStorage.removeItem(TOKEN_KEY);
        } catch {
            /* приватний режим — кошик житиме до перезавантаження */
        }
    },
};
const withToken = () => {
    const token = cartToken.get();
    return token ? { headers: { 'X-Cart-Token': token } } : {};
};

export type OrderWithDownloads = Order & { downloads: Record<string, DownloadFile[]> };
const withDownloads = (p: Promise<{ data: { data: Order; downloads?: Record<string, DownloadFile[]> } }>) =>
    p.then((r): OrderWithDownloads => ({ ...r.data.data, downloads: r.data.downloads ?? {} }));

export const cartApi = {
    show: () => unwrap<Cart>(http.get('/cart', withToken())),
    add: (product_uuid: string, variant_uuid: string | null, quantity: number) =>
        unwrap<Cart>(http.post('/cart/items', { product_uuid, variant_uuid, quantity }, withToken())),
    update: (item: string, quantity: number) => unwrap<Cart>(http.patch(`/cart/items/${item}`, { quantity }, withToken())),
    remove: (item: string) => unwrap<Cart>(http.delete(`/cart/items/${item}`, withToken())),
    claim: () => unwrap<Cart>(http.post('/cart/claim', {}, withToken())),
};

export const checkoutApi = {
    quote: (country_code: string | null) => unwrap<Quote>(http.post('/checkout/quote', { country_code })),
    place: (payload: { payment_method: PaymentMethod; delivery: DeliveryAddress | null; expected_total_minor: number }) =>
        unwrap<Order>(http.post('/checkout', payload)),
};

export const ordersApi = {
    async mine(page = 1): Promise<Paginated<Order>> {
        const { data } = await http.get<Paginated<Order>>('/me/orders', { params: { page } });
        return data;
    },
    show: (number: string) => withDownloads(http.get(`/me/orders/${encodeURIComponent(number)}`)),
    cancel: (number: string) => withDownloads(http.post(`/me/orders/${encodeURIComponent(number)}/cancel`)),
    /** Лише локально, доки не підключено Stripe */
    testPayment: (number: string) => withDownloads(http.post(`/me/orders/${encodeURIComponent(number)}/test-payment`)),
    downloadUrl: (number: string, item: string, file: string) =>
        `/api/v1/me/orders/${encodeURIComponent(number)}/items/${item}/files/${file}`,

    // ---- майстер ----
    async sales(status?: GroupStatus): Promise<Paginated<Sale>> {
        const { data } = await http.get<Paginated<Sale>>('/me/sales', { params: status ? { status } : {} });
        return data;
    },
    ship: (group: string, tracking_number: string | null) => unwrap<Sale>(http.post(`/me/sales/${group}/ship`, { tracking_number })),
    deliver: (group: string) => unwrap<Sale>(http.post(`/me/sales/${group}/deliver`)),
    cancelSale: (group: string, reason: string) => unwrap<Sale>(http.post(`/me/sales/${group}/cancel`, { reason })),

    // ---- адміністратор ----
    async all(params: { status?: OrderStatus; q?: string; page?: number }): Promise<Paginated<Order>> {
        const { data } = await http.get<Paginated<Order>>('/admin/orders', { params });
        return data;
    },
};
