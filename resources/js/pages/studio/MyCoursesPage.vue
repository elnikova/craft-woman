<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import ProductCover from '@/components/products/ProductCover.vue';
import ProductStatusBadge from '@/components/products/ProductStatusBadge.vue';
import { coursesApi } from '@/api/courses';
import { toApiError } from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import type { CourseCard, CourseStatus } from '@/types/course';
import { accessLabel } from '@/utils/course';
import { formatPrice } from '@/utils/format';
import { PRODUCT_STATUS_LABELS } from '@/utils/productStatus';

const auth = useAuthStore();
const canTeach = computed(() => auth.user?.maker_status === 'approved');

const TABS: (CourseStatus | 'all')[] = ['all', 'draft', 'pending_review', 'published', 'rejected', 'archived'];
const { t } = useI18n();
const tab = ref<CourseStatus | 'all'>('all');
const items = ref<CourseCard[]>([]);
const loading = ref(true);
const error = ref('');

async function load() {
    loading.value = true;
    error.value = '';
    try {
        items.value = (await coursesApi.mine(tab.value === 'all' ? undefined : tab.value)).data;
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
            <h1 class="font-flair m-0 text-[40px] leading-tight font-bold tracking-[-0.015em] text-plum">{{ t('myCourses.title') }}</h1>
            <BaseButton v-if="canTeach" :to="{ name: 'studio-course-new' }">{{ t('myCourses.add') }}</BaseButton>
        </div>

        <div v-if="!canTeach" class="mt-6 rounded-card bg-notice-bg p-5 text-notice-ink">
            <p class="m-0 font-semibold">{{ t('myCourses.notApproved') }}</p>
            <p class="mt-1 mb-0">
                <RouterLink :to="{ name: 'studio' }" class="font-semibold text-notice-ink">{{ t('myProducts.checkProfile') }}</RouterLink>
            </p>
        </div>

        <div role="tablist" :aria-label="t('adminCourses.status')" class="mt-8 flex flex-wrap gap-2">
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
                {{ tabKey === 'all' ? t('myProducts.all') : PRODUCT_STATUS_LABELS[tabKey] }}
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
                {{ tab === 'all' ? t('myCourses.empty') : t('myCourses.emptyStatus', { status: PRODUCT_STATUS_LABELS[tab] }) }}
            </p>
            <BaseButton v-if="canTeach && tab === 'all'" :to="{ name: 'studio-course-new' }">{{ t('myCourses.addFirst') }}</BaseButton>
        </div>

        <ul v-else class="mt-8 mb-0 flex list-none flex-col gap-3 p-0">
            <li v-for="c in items" :key="c.uuid">
                <RouterLink
                    :to="{ name: 'studio-course', params: { uuid: c.uuid } }"
                    class="flex flex-wrap items-center gap-5 rounded-card border border-line p-4 text-inherit no-underline hover:border-plum"
                >
                    <ProductCover :uuid="c.uuid" :name="c.name" :src="c.cover_url" ratio="wide" class="!w-32 shrink-0" />
                    <div class="min-w-0 flex-[1_1_240px]">
                        <div class="text-lg font-semibold text-plum">{{ c.name }}</div>
                        <div class="mt-1 text-sm text-ink-muted">
                            {{ t('coursePage.modules', { n: c.modules_count }) }}, {{ t('coursePage.lessons', { n: c.lessons_count }) }} · {{ accessLabel(c) }}
                        </div>
                        <p v-if="c.status === 'rejected' && c.review_note" class="mt-2 mb-0 text-sm text-kalyna">{{ t('myProducts.note', { note: c.review_note }) }}</p>
                    </div>
                    <div class="w-24 text-right font-bold text-plum">{{ formatPrice(c.price.amount_minor, c.price.currency) }}</div>
                    <ProductStatusBadge v-if="c.status" :status="c.status" />
                </RouterLink>
            </li>
        </ul>
    </div>
</template>
