<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import StarOrnament from '@/components/ornament/StarOrnament.vue';

const props = defineProps<{ section: string }>();

/** Section slug → message key */
const TITLES: Record<string, string> = {
    orders: 'accountNav.orders',
    learning: 'accountNav.learning',
    bookings: 'accountNav.bookings',
    favorites: 'accountNav.favorites',
    settings: 'accountNav.settings',
    products: 'accountNav.products',
    teaching: 'accountNav.teaching',
    workshops: 'accountNav.workshops',
    sales: 'accountNav.sales',
    users: 'accountNav.users',
    categories: 'accountNav.categories',
    moderation: 'soon.moderation',
    'all-orders': 'accountNav.allOrders',
};

const { t } = useI18n();
const title = computed(() => t(TITLES[props.section] ?? 'soon.section'));
</script>

<template>
    <div class="flex max-w-[640px] flex-col items-start gap-5">
        <StarOrnament :size="96" />
        <h1 class="font-flair m-0 text-[36px] leading-tight font-bold text-plum">{{ title }}</h1>
        <p class="m-0 text-lg text-ink-muted">{{ t('soon.text') }}</p>
        <BaseButton variant="secondary" :to="{ name: 'account' }">{{ t('soon.toOverview') }}</BaseButton>
    </div>
</template>
