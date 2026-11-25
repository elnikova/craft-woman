<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import ProductCover from '@/components/products/ProductCover.vue';
import { coursesApi } from '@/api/courses';
import { toApiError } from '@/api/http';
import type { LearningItem } from '@/types/course';
import { formatDate } from '@/utils/orderStatus';

const { t } = useI18n();
const items = ref<LearningItem[]>([]);
const loading = ref(true);
const error = ref('');

async function load() {
    loading.value = true;
    error.value = '';
    try {
        items.value = await coursesApi.learning();
    } catch (e) {
        error.value = toApiError(e).message;
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="max-w-[1080px]">
        <h1 class="font-flair m-0 text-[40px] leading-tight font-bold tracking-[-0.015em] text-plum">{{ t('learning.title') }}</h1>

        <div v-if="loading" class="mt-8 space-y-3" aria-busy="true" :aria-label="t('common.loading')">
            <div v-for="i in 2" :key="i" class="h-32 animate-pulse rounded-card bg-lavender-50" />
        </div>
        <div v-else-if="error" role="alert" class="mt-8 flex flex-col items-start gap-4 rounded-card border border-kalyna/30 bg-kalyna/5 p-6">
            <p class="m-0 text-kalyna">{{ error }}</p>
            <BaseButton variant="secondary" @click="load">{{ t('common.retry') }}</BaseButton>
        </div>
        <div v-else-if="items.length === 0" class="mt-10 flex flex-col items-start gap-4">
            <p class="m-0 text-lg text-ink-muted">{{ t('learning.empty') }}</p>
            <BaseButton :to="{ name: 'courses' }">{{ t('learning.browse') }}</BaseButton>
        </div>

        <ul v-else class="mt-8 mb-0 flex list-none flex-col gap-4 p-0">
            <li v-for="i in items" :key="i.course.uuid" class="flex flex-wrap items-center gap-5 rounded-card border border-line bg-white p-4">
                <ProductCover :uuid="i.course.uuid" :name="i.course.name" :src="i.course.cover_url" ratio="wide" class="!w-44 shrink-0" />
                <div class="min-w-0 flex-[1_1_260px]">
                    <RouterLink :to="{ name: 'course', params: { slug: i.course.slug } }" class="text-lg font-semibold text-plum no-underline hover:underline" :lang="i.course.content_locale">
                        {{ i.course.name }}
                    </RouterLink>
                    <div class="text-sm text-ink-muted">{{ i.course.maker?.display_name }}</div>
                    <div class="mt-3 flex items-center gap-3">
                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-lavender-100" role="progressbar" :aria-valuenow="i.percent" aria-valuemin="0" aria-valuemax="100" :aria-label="i.course.name">
                            <div class="h-full rounded-full bg-plum" :style="{ width: `${i.percent}%` }" />
                        </div>
                        <span class="text-sm font-semibold text-plum">{{ i.percent }}%</span>
                    </div>
                    <div class="mt-1 text-sm text-ink-muted">
                        {{ t('coursePage.progress', { done: i.completed, total: i.total }) }}
                        <template v-if="!i.has_access"> · <span class="text-kalyna">{{ t('learning.expired') }}</span></template>
                        <template v-else-if="i.expires_at"> · {{ t('coursePage.expiresOn', { date: formatDate(i.expires_at) }) }}</template>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <BaseButton v-if="i.certificate_number" variant="secondary" :to="{ name: 'certificate', params: { number: i.certificate_number } }">
                        {{ t('learning.certificate') }}
                    </BaseButton>
                    <BaseButton :to="{ name: 'course', params: { slug: i.course.slug } }">{{ t('learning.continue') }}</BaseButton>
                </div>
            </li>
        </ul>
    </div>
</template>
