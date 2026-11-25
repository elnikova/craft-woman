<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import ProductCover from '@/components/products/ProductCover.vue';
import WorkshopStatusBadge from '@/components/workshops/WorkshopStatusBadge.vue';
import { workshopsApi } from '@/api/workshops';
import { toApiError } from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import type { WorkshopCard, WorkshopStatus } from '@/types/workshop';
import { formatPrice } from '@/utils/format';
import { WORKSHOP_STATUS_LABELS, dayLabel } from '@/utils/workshop';

const auth = useAuthStore();
const canHost = computed(() => auth.user?.maker_status === 'approved');

const TABS: (WorkshopStatus | 'all')[] = ['all', 'draft', 'pending_review', 'published', 'rejected', 'archived', 'cancelled'];
const { t } = useI18n();
const tab = ref<WorkshopStatus | 'all'>('all');
const items = ref<WorkshopCard[]>([]);
const loading = ref(true);
const error = ref('');

async function load() {
    loading.value = true;
    error.value = '';
    try {
        items.value = (await workshopsApi.mine(tab.value === 'all' ? undefined : tab.value)).data;
    } catch (e) {
        error.value = toApiError(e).message;
    } finally {
        loading.value = false;
    }
}

watch(tab, load);
onMounted(load);
</script>

<template>
    <div class="max-w-[1080px]">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <h1 class="font-flair m-0 text-[40px] leading-tight font-bold tracking-[-0.015em] text-plum">{{ t('myWorkshops.title') }}</h1>
            <BaseButton v-if="canHost" :to="{ name: 'studio-workshop-new' }">{{ t('myWorkshops.add') }}</BaseButton>
        </div>

        <div v-if="!canHost" class="mt-6 rounded-card bg-notice-bg p-5 text-notice-ink">
            <p class="m-0 font-semibold">{{ t('myWorkshops.notApproved') }}</p>
            <p class="mt-1 mb-0">
                <RouterLink :to="{ name: 'studio' }" class="font-semibold text-notice-ink">{{ t('myProducts.checkProfile') }}</RouterLink>
            </p>
        </div>

        <div role="tablist" :aria-label="t('adminWorkshops.status')" class="mt-8 flex flex-wrap gap-2">
            <button
                v-for="tabKey in TABS"
                :key="tabKey"
                type="button"
                role="tab"
                :aria-selected="tab === tabKey"
                class="h-11 rounded-full border px-[18px] text-[15px]"
                :class="tab === tabKey ? 'border-plum bg-plum text-white' : 'border-line-strong bg-white text-ink hover:border-plum'"
                @click="tab = tabKey"
            >
                {{ tabKey === 'all' ? t('myProducts.all') : WORKSHOP_STATUS_LABELS[tabKey] }}
            </button>
        </div>

        <div v-if="loading" class="mt-8 space-y-3" aria-busy="true" :aria-label="t('common.loading')">
            <div v-for="i in 3" :key="i" class="h-24 animate-pulse rounded-card bg-lavender-50" />
        </div>

        <div v-else-if="error" role="alert" class="mt-8 flex flex-col items-start gap-4 rounded-card border border-kalyna/30 bg-kalyna/5 p-6">
            <p class="m-0 text-kalyna">{{ error }}</p>
            <BaseButton variant="secondary" @click="load">{{ t('common.retry') }}</BaseButton>
        </div>

        <div v-else-if="items.length === 0" class="mt-10 flex flex-col items-start gap-4">
            <p class="m-0 text-lg text-ink-muted">
                {{ tab === 'all' ? t('myWorkshops.empty') : t('myWorkshops.emptyStatus', { status: WORKSHOP_STATUS_LABELS[tab] }) }}
            </p>
            <BaseButton v-if="canHost && tab === 'all'" :to="{ name: 'studio-workshop-new' }">{{ t('myWorkshops.addFirst') }}</BaseButton>
        </div>

        <ul v-else class="mt-8 mb-0 flex list-none flex-col gap-3 p-0">
            <li v-for="w in items" :key="w.uuid">
                <RouterLink
                    :to="{ name: 'studio-workshop', params: { uuid: w.uuid } }"
                    class="flex flex-wrap items-center gap-5 rounded-card border border-line p-4 text-inherit no-underline hover:border-plum"
                >
                    <ProductCover :uuid="w.uuid" :name="w.name" :src="w.cover_url" ratio="wide" class="!w-32 shrink-0" />
                    <div class="min-w-0 flex-[1_1_240px]">
                        <div class="text-lg font-semibold text-plum">{{ w.name }}</div>
                        <div class="mt-1 text-sm text-ink-muted">
                            {{ w.starts_at ? dayLabel(w.starts_at, w.timezone) : t('myWorkshops.noDate') }}
                            · {{ w.format === 'online' ? t('workshop.online') : w.city || t('workshop.offline') }}
                        </div>
                        <p v-if="w.status === 'rejected' && w.review_note" class="mt-2 mb-0 text-sm text-kalyna">{{ t('myProducts.note', { note: w.review_note }) }}</p>
                    </div>
                    <div class="w-36 text-right text-[15px] text-ink-muted">{{ t('myWorkshops.booked', { booked: w.seats_booked ?? 0, capacity: w.capacity }) }}</div>
                    <div class="w-20 text-right font-bold text-plum">{{ formatPrice(w.price.amount_minor, w.price.currency) }}</div>
                    <WorkshopStatusBadge v-if="w.status" :status="w.status" />
                </RouterLink>
            </li>
        </ul>
    </div>
</template>
