<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import BaseButton from '@/components/ui/BaseButton.vue';
import OrnamentBand from '@/components/ornament/OrnamentBand.vue';
import WorkshopRow from '@/components/workshops/WorkshopRow.vue';
import { workshopsApi } from '@/api/workshops';
import { toApiError } from '@/api/http';
import type { WorkshopCard, WorkshopFormat } from '@/types/workshop';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const str = (v: unknown) => (typeof v === 'string' ? v : '');

const query = ref(str(route.query.q));
const city = ref(str(route.query.city));
const format = ref<WorkshopFormat | ''>(route.query.format === 'online' || route.query.format === 'offline' ? route.query.format : '');
const items = ref<WorkshopCard[]>([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(true);
const error = ref('');
const filtered = computed(() => !!(query.value.trim() || city.value.trim() || format.value));

const FORMATS = computed(() => [
    { id: '' as const, label: t('workshops.all') },
    { id: 'offline' as const, label: t('workshop.offline') },
    { id: 'online' as const, label: t('workshop.online') },
]);

async function load(p = 1) {
    loading.value = true;
    error.value = '';
    try {
        const res = await workshopsApi.list({
            q: query.value.trim() || undefined,
            city: format.value !== 'online' ? city.value.trim() || undefined : undefined,
            format: format.value || undefined,
            page: p,
        });
        items.value = p === 1 ? res.data : [...items.value, ...res.data];
        page.value = res.meta.current_page;
        lastPage.value = res.meta.last_page;
        total.value = res.meta.total;
    } catch (e) {
        error.value = toApiError(e).message;
    } finally {
        loading.value = false;
    }
}

function apply() {
    const q: Record<string, string> = {};
    if (query.value.trim()) q.q = query.value.trim();
    if (format.value) q.format = format.value;
    if (city.value.trim() && format.value !== 'online') q.city = city.value.trim();
    void router.replace({ query: q });
    void load();
}

function setFormat(f: WorkshopFormat | '') {
    format.value = f;
    apply();
}

function reset() {
    query.value = '';
    city.value = '';
    format.value = '';
    apply();
}

onMounted(() => load());
</script>

<template>
    <div>
        <section class="tx-weave bg-lavender-50">
            <div class="mx-auto max-w-[1280px] px-6 pt-12 pb-10 md:px-8">
                <h1 class="font-flair m-0 text-[44px] leading-tight font-bold tracking-[-0.02em] text-plum md:text-[56px]">{{ t('workshops.title') }}</h1>
                <p class="mt-3 mb-0 max-w-[620px] text-lg text-ink-soft">{{ t('workshops.lead') }}</p>
                <form class="mt-8 flex max-w-[860px] flex-wrap items-end gap-3" role="search" @submit.prevent="apply">
                    <div class="flex min-w-[240px] flex-[2_1_280px] flex-col gap-1.5">
                        <label for="ws-q" class="text-[15px] font-semibold text-ink">{{ t('workshops.searchLabel') }}</label>
                        <input
                            id="ws-q"
                            v-model="query"
                            type="search"
                            :placeholder="t('workshops.searchPlaceholder')"
                            class="h-[52px] rounded-control border-[1.5px] border-line-strong bg-white px-4 text-base focus:border-plum focus:outline-none"
                        />
                    </div>
                    <div v-if="format !== 'online'" class="flex min-w-[160px] flex-[1_1_180px] flex-col gap-1.5">
                        <label for="ws-city" class="text-[15px] font-semibold text-ink">{{ t('workshops.city') }}</label>
                        <input
                            id="ws-city"
                            v-model="city"
                            type="search"
                            :placeholder="t('workshops.cityPlaceholder')"
                            class="h-[52px] rounded-control border-[1.5px] border-line-strong bg-white px-4 text-base focus:border-plum focus:outline-none"
                        />
                    </div>
                    <BaseButton type="submit" size="lg">{{ t('workshops.search') }}</BaseButton>
                </form>
                <div role="radiogroup" :aria-label="t('workshops.formatLabel')" class="mt-5 flex flex-wrap gap-2">
                    <button
                        v-for="f in FORMATS"
                        :key="f.id"
                        type="button"
                        role="radio"
                        :aria-checked="format === f.id"
                        class="h-11 rounded-full border px-[18px] text-[15px]"
                        :class="format === f.id ? 'border-plum bg-plum text-white' : 'border-line-strong bg-white text-ink hover:border-plum'"
                        @click="setFormat(f.id)"
                    >
                        {{ f.label }}
                    </button>
                </div>
            </div>
            <OrnamentBand start="rhombus" />
        </section>

        <div class="mx-auto max-w-[1280px] px-6 py-10 md:px-8" aria-live="polite" :aria-busy="loading">
            <div v-if="loading && items.length === 0" class="space-y-3" :aria-label="t('common.loading')">
                <div v-for="i in 3" :key="i" class="h-24 animate-pulse rounded-card bg-lavender-50" />
            </div>

            <div v-else-if="error" role="alert" class="flex flex-col items-start gap-4 rounded-card border border-kalyna/30 bg-kalyna/5 p-6">
                <p class="m-0 text-kalyna">{{ error }}</p>
                <BaseButton variant="secondary" @click="load()">{{ t('common.retry') }}</BaseButton>
            </div>

            <div v-else-if="items.length === 0" class="flex flex-col items-start gap-3 py-8">
                <p class="m-0 text-lg text-ink-muted">{{ filtered ? t('workshops.emptyFiltered') : t('workshops.empty') }}</p>
                <BaseButton v-if="filtered" variant="secondary" @click="reset">{{ t('workshops.reset') }}</BaseButton>
            </div>

            <template v-else>
                <p class="m-0 text-[15px] text-ink-muted">{{ t('workshops.found', { n: total }) }}</p>
                <ul class="mt-4 mb-0 list-none border-t border-line p-0">
                    <WorkshopRow v-for="w in items" :key="w.uuid" :workshop="w" />
                </ul>
                <div v-if="page < lastPage" class="mt-8 flex justify-center">
                    <BaseButton variant="secondary" :loading="loading" @click="load(page + 1)">{{ t('workshops.more') }}</BaseButton>
                </div>
            </template>
        </div>
    </div>
</template>
