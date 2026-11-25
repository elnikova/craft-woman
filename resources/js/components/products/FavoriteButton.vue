<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useFavoritesStore } from '@/stores/favorites';

const props = withDefaults(defineProps<{ uuid: string; name: string; size?: 'md' | 'lg' }>(), { size: 'md' });

const auth = useAuthStore();
const { t } = useI18n();
const favorites = useFavoritesStore();
const router = useRouter();
const route = useRoute();
const failed = ref(false);

const active = computed(() => favorites.has(props.uuid));

async function toggle() {
    // Send guests to login and bring them back to the same page
    if (!auth.isAuthenticated) {
        await router.push({ name: 'login', query: { redirect: route.fullPath } });
        return;
    }
    failed.value = false;
    try {
        await favorites.toggle(props.uuid);
    } catch {
        failed.value = true;
    }
}
</script>

<template>
    <button
        type="button"
        class="grid place-items-center rounded-full bg-white text-plum transition-colors hover:bg-lavender-50"
        :class="[size === 'lg' ? 'h-[52px] w-[52px] border-[1.5px] border-line-strong' : 'h-11 w-11', failed ? 'ring-2 ring-kalyna' : '']"
        :aria-pressed="active"
        :aria-label="active ? t('product.removeFavorite', { name }) : t('product.addFavorite', { name })"
        :title="failed ? t('product.favoriteFailed') : undefined"
        @click.prevent="toggle"
    >
        <svg
            :width="size === 'lg' ? 22 : 20"
            :height="size === 'lg' ? 22 : 20"
            viewBox="0 0 24 24"
            :fill="active ? 'currentColor' : 'none'"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
        >
            <path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z" />
        </svg>
    </button>
</template>
