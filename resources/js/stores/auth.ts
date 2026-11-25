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
    /** Має профіль майстра (будь-який статус) — показуємо вхід у «Мою майстерню» */
    const hasStudio = computed(() => (user.value?.maker_status ?? null) !== null);
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

    function setMakerStatus(status: User['maker_status']) {
        if (user.value) user.value = { ...user.value, maker_status: status };
    }

    function setUser(next: User) {
        user.value = next;
    }

    return { user, checked, setUser, isAuthenticated, isMaster, isAdmin, hasStudio, fetchUser, login, register, logout, setMakerStatus };
});
