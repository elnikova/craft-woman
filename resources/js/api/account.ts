import { http } from './http';
import type { AdminUser, Paginated, Role, User, UserStatus, Wrapped } from '@/types/user';

const unwrap = <T>(p: Promise<{ data: Wrapped<T> }>) => p.then((r) => r.data.data);

export const accountApi = {
    update: (payload: { name: string; email: string; current_password?: string }) => unwrap<User>(http.patch('/me/account', payload)),
    changePassword: (payload: { current_password: string; password: string; password_confirmation: string }) =>
        http.put<{ message: string }>('/me/password', payload).then((r) => r.data.message),
};

export const adminUsersApi = {
    async list(params: { q?: string; role?: Role; status?: UserStatus; page?: number }): Promise<Paginated<AdminUser>> {
        const { data } = await http.get<Paginated<AdminUser>>('/admin/users', { params });
        return data;
    },
    block: (uuid: string, reason: string) => unwrap<AdminUser>(http.post(`/admin/users/${uuid}/block`, { reason })),
    unblock: (uuid: string) => unwrap<AdminUser>(http.post(`/admin/users/${uuid}/unblock`)),
    setRole: (uuid: string, role: Extract<Role, 'buyer' | 'admin'>) => unwrap<AdminUser>(http.patch(`/admin/users/${uuid}/role`, { role })),
};
