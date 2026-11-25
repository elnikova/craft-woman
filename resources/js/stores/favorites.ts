import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { favoritesApi } from '@/api/favorites';

/** Favorite product UUIDs, so card hearts know their state without extra requests */
export const useFavoritesStore = defineStore('favorites', () => {
    const ids = ref<Set<string>>(new Set());
    const count = computed(() => ids.value.size);

    const has = (uuid: string) => ids.value.has(uuid);

    async function load(isAuthenticated: boolean) {
        if (!isAuthenticated) {
            ids.value = new Set();
            return;
        }
        try {
            ids.value = new Set(await favoritesApi.ids());
        } catch {
            ids.value = new Set();
        }
    }

    /** Optimistic: the heart flips at once and rolls back on error */
    async function toggle(uuid: string): Promise<boolean> {
        const was = has(uuid);
        const next = new Set(ids.value);
        if (was) next.delete(uuid);
        else next.add(uuid);
        ids.value = next;
        try {
            await (was ? favoritesApi.remove(uuid) : favoritesApi.add(uuid));
            return !was;
        } catch (e) {
            const back = new Set(ids.value);
            if (was) back.add(uuid);
            else back.delete(uuid);
            ids.value = back;
            throw e;
        }
    }

    return { ids, count, has, load, toggle };
});
