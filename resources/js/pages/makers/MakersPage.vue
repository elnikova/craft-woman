<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import BaseButton from '@/components/ui/BaseButton.vue';
import MakerProfileCard from '@/components/makers/MakerProfileCard.vue';
import OrnamentBand from '@/components/ornament/OrnamentBand.vue';
import { makersApi } from '@/api/makers';
import { toApiError } from '@/api/http';
import type { MakerProfile } from '@/types/maker';
import { plural } from '@/utils/format';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();

const query = ref(typeof route.query.q === 'string' ? route.query.q : '');
const makers = ref<MakerProfile[]>([]);
const total = ref(0);
const page = ref(1);
const lastPage = ref(1);
const loading = ref(true);
const loadingMore = ref(false);
const error = ref('');

async function load(reset = true) {
    error.value = '';
    if (reset) {
        loading.value = true;
        page.value = 1;
    } else {
        loadingMore.value = true;
    }
    try {
        const res = await makersApi.list({ page: page.value, q: query.value || undefined, per_page: 12 });
        makers.value = reset ? res.data : [...makers.value, ...res.data];
        total.value = res.meta.total;
        lastPage.value = res.meta.last_page;
    } catch (e) {
        error.value = toApiError(e).message;
    } finally {
        loading.value = false;
        loadingMore.value = false;
    }
}

function loadMore() {
    page.value += 1;
    void load(false);
}

function search() {
    void router.replace({ query: query.value ? { q: query.value } : {} });
}

watch(
    () => route.query.q,
    (q) => {
        query.value = typeof q === 'string' ? q : '';
        void load();
    },
);

onMounted(() => load());
</script>

<template>
    <section class="tx-linen bg-lavender-100">
        <div class="mx-auto max-w-[1280px] px-6 pt-10 pb-12 md:px-8">
            <h1 class="font-flair m-0 text-[44px] leading-tight font-bold tracking-[-0.02em] text-plum md:text-[56px]">{{ t('makers.title') }}</h1>
            <p class="mt-3 mb-0 max-w-[600px] text-lg text-ink-soft">{{ t('makers.lead') }}</p>

            <form role="search" class="mt-8 flex max-w-[640px] flex-wrap gap-2" @submit.prevent="search">
                <label for="makers-q" class="sr-only">{{ t('makers.searchLabel') }}</label>
                <input
                    id="makers-q"
                    v-model="query"
                    type="search"
                    :placeholder="t('makers.searchPlaceholder')"
                    class="h-[52px] min-w-0 flex-[1_1_280px] rounded-control border-[1.5px] border-lavender-200 bg-white px-4 text-base text-ink focus:border-plum focus:outline-none"
                />
                <BaseButton type="submit" size="lg">{{ t('makers.search') }}</BaseButton>
            </form>
        </div>
        <OrnamentBand start="tree" />
    </section>

    <section class="mx-auto max-w-[1280px] px-6 py-16 md:px-8" aria-live="polite" :aria-busy="loading">
        <!-- Loading -->
        <div v-if="loading" class="grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-6" :aria-label="t('common.loading')">
            <div v-for="i in 8" :key="i" class="flex animate-pulse flex-col gap-3">
                <div class="aspect-[4/5] rounded-card bg-lavender-50" />
                <div class="h-5 w-2/3 rounded bg-lavender-50" />
                <div class="h-4 w-1/3 rounded bg-lavender-50" />
            </div>
        </div>

        <!-- Error -->
        <div v-else-if="error" role="alert" class="flex flex-col items-start gap-4 rounded-card border border-kalyna/30 bg-kalyna/5 p-6">
            <p class="m-0 text-kalyna">{{ error }}</p>
            <BaseButton variant="secondary" @click="load()">{{ t('common.retry') }}</BaseButton>
        </div>

        <!-- Empty -->
        <div v-else-if="makers.length === 0" class="flex flex-col items-start gap-4">
            <h2 class="font-flair m-0 text-2xl font-bold text-plum">
                {{ query ? t('makers.noResults', { query }) : t('makers.emptyTitle') }}
            </h2>
            <p class="m-0 text-ink-muted">
                {{ query ? t('makers.noResultsHint') : t('makers.emptyHint') }}
            </p>
            <BaseButton v-if="query" variant="secondary" @click="query = ''; search()">{{ t('makers.clearSearch') }}</BaseButton>
            <BaseButton v-else :to="{ name: 'register', query: { role: 'master' } }">{{ t('makers.openStudio') }}</BaseButton>
        </div>

        <!-- List -->
        <template v-else>
            <p class="mt-0 mb-8 text-ink-muted">{{ plural(total, 'makers') }}</p>
            <div class="grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-x-6 gap-y-10">
                <MakerProfileCard v-for="maker in makers" :key="maker.uuid" :maker="maker" />
            </div>
            <div v-if="page < lastPage" class="mt-12 flex justify-center">
                <BaseButton variant="secondary" :loading="loadingMore" @click="loadMore">{{ t('common.showMore') }}</BaseButton>
            </div>
        </template>
    </section>
</template>
