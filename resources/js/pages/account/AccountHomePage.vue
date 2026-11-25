<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { RouteLocationRaw } from 'vue-router';
import BaseButton from '@/components/ui/BaseButton.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import MakerStatusBadge from '@/components/makers/MakerStatusBadge.vue';
import { makersApi } from '@/api/makers';
import type { IconName } from '@/config/accountNav';
import { useAuthStore } from '@/stores/auth';
import type { MakerProfile } from '@/types/maker';

const auth = useAuthStore();
const { t } = useI18n();

const firstName = computed(() => auth.user?.name.split(/\s+/)[0] ?? '');
const isAdmin = computed(() => auth.user?.role === 'admin');
const isMaker = computed(() => !isAdmin.value && (auth.user?.role === 'master' || auth.hasStudio));

const pendingMakers = ref<number | null>(null);
const myProfile = ref<MakerProfile | null>(null);

onMounted(async () => {
    try {
        if (isAdmin.value) pendingMakers.value = (await makersApi.reviewQueue('pending_review')).meta.total;
        if (isMaker.value) myProfile.value = await makersApi.mine();
    } catch {
        /* the cards just show their default state */
    }
});

interface Card {
    icon: IconName;
    title: string;
    text: string;
    to?: RouteLocationRaw;
}

const profileHint = computed(() => {
    const status = myProfile.value?.status ?? 'draft';
    return t(`accountHome.${status === 'pending_review' ? 'pending' : status}`);
});

const adminCards = computed<Card[]>(() => [
    { icon: 'users', title: t('accountNav.users'), text: t('accountHome.cards.users'), to: { name: 'admin-users' } },
    { icon: 'flag', title: t('accountNav.adminProducts'), text: t('accountHome.cards.adminProducts'), to: { name: 'admin-products' } },
    { icon: 'bag', title: t('accountNav.allOrders'), text: t('accountHome.cards.allOrders'), to: { name: 'admin-orders' } },
]);

const makerCards = computed<Card[]>(() => [
    { icon: 'box', title: t('accountNav.products'), text: t('accountHome.cards.products'), to: { name: 'studio-products' } },
    { icon: 'chart', title: t('accountNav.sales'), text: t('accountHome.cards.sales'), to: { name: 'studio-sales' } },
    { icon: 'book', title: t('accountNav.teaching'), text: t('accountHome.cards.courses') },
]);

const buyerCards = computed<Card[]>(() => [
    { icon: 'bag', title: t('accountNav.orders'), text: t('accountHome.cards.orders'), to: { name: 'account-orders' } },
    { icon: 'heart', title: t('accountNav.favorites'), text: t('accountHome.cards.favorites'), to: { name: 'account-favorites' } },
    { icon: 'settings', title: t('accountNav.settings'), text: t('accountHome.cards.settings'), to: { name: 'account-settings' } },
]);
</script>

<template>
    <div class="max-w-[1080px]">
        <h1 class="font-flair m-0 text-[36px] leading-tight font-bold tracking-[-0.015em] text-plum md:text-[44px]">
            {{ t('accountHome.greeting', { name: firstName }) }}
        </h1>

        <!-- Administrator -->
        <div v-if="isAdmin" class="mt-8 grid gap-5 md:grid-cols-2">
            <RouterLink :to="{ name: 'admin-makers' }" class="group flex flex-col gap-3 rounded-card border border-line p-6 text-inherit no-underline hover:border-plum">
                <span class="grid h-11 w-11 place-items-center rounded-full bg-lavender-50 text-plum"><AppIcon name="check" /></span>
                <span class="text-[15px] text-ink-muted">{{ t('accountHome.pendingMakers') }}</span>
                <span class="font-flair text-5xl font-bold text-plum">{{ pendingMakers ?? '—' }}</span>
                <span class="font-semibold text-plum group-hover:underline">{{ t('accountHome.toQueue') }}</span>
            </RouterLink>
        </div>

        <!-- Maker -->
        <div v-else-if="isMaker" class="mt-8 flex flex-wrap items-center justify-between gap-6 rounded-card bg-lavender-50 p-6">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-lg font-semibold text-plum">{{ t('accountHome.studioProfile') }}</span>
                    <MakerStatusBadge v-if="myProfile?.status" :status="myProfile.status" />
                </div>
                <p class="mt-2 mb-0 text-ink-muted">{{ profileHint }}</p>
            </div>
            <BaseButton :to="{ name: 'studio' }">{{ t('accountHome.openProfile') }}</BaseButton>
        </div>

        <div class="mt-5 grid gap-5 md:grid-cols-3">
            <component
                :is="c.to ? 'RouterLink' : 'div'"
                v-for="c in isAdmin ? adminCards : isMaker ? [...makerCards, ...buyerCards.slice(0, 2)] : buyerCards"
                :key="c.title"
                :to="c.to"
                class="flex flex-col gap-2 rounded-card border p-6 text-inherit no-underline"
                :class="c.to ? 'border-line hover:border-plum' : 'border-dashed border-line-strong'"
            >
                <span class="grid h-11 w-11 place-items-center rounded-full bg-lavender-50 text-plum"><AppIcon :name="c.icon" /></span>
                <span class="font-semibold text-plum">{{ c.title }}</span>
                <span class="text-ink-muted">{{ c.text }}</span>
            </component>
        </div>

        <div v-if="!isAdmin && !isMaker" class="mt-5 flex flex-wrap items-center justify-between gap-6 rounded-card bg-lavender-50 p-6">
            <div>
                <div class="text-lg font-semibold text-plum">{{ t('accountHome.lookingTitle') }}</div>
                <p class="mt-1 mb-0 text-ink-muted">{{ t('accountHome.lookingText') }}</p>
            </div>
            <BaseButton :to="{ name: 'makers' }">{{ t('accountHome.seeMakers') }}</BaseButton>
        </div>
    </div>
</template>
