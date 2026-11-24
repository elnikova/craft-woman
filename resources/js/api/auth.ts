import { ensureCsrfCookie, http } from './http';
import type { LoginPayload, RegisterPayload, User, Wrapped } from '@/types/user';

export const authApi = {
    async login(payload: LoginPayload): Promise<User> {
        await ensureCsrfCookie();
        const { data } = await http.post<Wrapped<User>>('/login', payload);
        return data.data;
    },

    async register(payload: RegisterPayload): Promise<User> {
        await ensureCsrfCookie();
        const { data } = await http.post<Wrapped<User>>('/register', payload);
        return data.data;
    },

    async me(): Promise<User> {
        const { data } = await http.get<Wrapped<User>>('/me');
        return data.data;
    },

    async logout(): Promise<void> {
        await http.post('/logout');
    },
};
