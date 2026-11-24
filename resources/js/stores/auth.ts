import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { authApi } from '@/api/auth';
import type { LoginPayload, RegisterPayload, User } from '@/types/user';

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null);
    /** true після першої перевірки сесії — щоб guard не блимав редиректами */
    const checked = ref(false);

    const isAuthenticated = computed(() => user.value !== null);
    const isMaster = computed(() => user.value?.role === 'master');
    const isAdmin = computed(() => user.value?.role === 'admin');

    async function fetchUser(): Promise<User | null> {
        try {
            user.value = await authApi.me();
        } catch {
            user.value = null; // 401 — гість, це нормально
        } finally {
            checked.value = true;
        }
        return user.value;
    }

    async function login(payload: LoginPayload): Promise<User> {
        user.value = await authApi.login(payload);
        return user.value;
    }

    async function register(payload: RegisterPayload): Promise<User> {
        user.value = await authApi.register(payload);
        return user.value;
    }

    async function logout(): Promise<void> {
        await authApi.logout();
        user.value = null;
    }

    return { user, checked, isAuthenticated, isMaster, isAdmin, fetchUser, login, register, logout };
});
