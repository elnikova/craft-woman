<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import BaseButton from '@/components/ui/BaseButton.vue';
import CourseCard from '@/components/courses/CourseCard.vue';
import OrnamentBand from '@/components/ornament/OrnamentBand.vue';
import { coursesApi } from '@/api/courses';
import { toApiError } from '@/api/http';
import type { CourseCard as Course } from '@/types/course';
import { plural } from '@/utils/format';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();

const query = ref(typeof route.query.q === 'string' ? route.query.q : '');
const courses = ref<Course[]>([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(true);
const error = ref('');

async function load(p = 1) {
    loading.value = true;
    error.value = '';
    try {
        const res = await coursesApi.list({ q: query.value.trim() || undefined, page: p });
        courses.value = p === 1 ? res.data : [...courses.value, ...res.data];
        page.value = res.meta.current_page;
        lastPage.value = res.meta.last_page;
        total.value = res.meta.total;
    } catch (e) {
        error.value = toApiError(e).message;
    } finally {
        loading.value = false;
    }
}

function search() {
    void router.replace({ query: query.value.trim() ? { q: query.value.trim() } : {} });
    void load();
}

onMounted(() => load());
</script>

<template>
    <div>
        <section class="tx-weave bg-lavender-50">
            <div class="mx-auto max-w-[1280px] px-6 pt-12 pb-10 md:px-8">
                <h1 class="font-flair m-0 text-[44px] leading-tight font-bold tracking-[-0.02em] text-plum md:text-[56px]">{{ t('courses.title') }}</h1>
                <p class="mt-3 mb-0 max-w-[620px] text-lg text-ink-soft">{{ t('courses.lead') }}</p>
                <form class="mt-8 flex max-w-[640px] flex-wrap gap-3" role="search" @submit.prevent="search">
                    <label for="courses-q" class="sr-only">{{ t('courses.searchLabel') }}</label>
                    <input
                        id="courses-q"
                        v-model="query"
                        type="search"
                        :placeholder="t('courses.searchPlaceholder')"
                        class="h-[52px] min-w-[240px] flex-1 rounded-control border-[1.5px] border-line-strong bg-white px-4 text-base focus:border-plum focus:outline-none"
                    />
                    <BaseButton type="submit" size="lg">{{ t('courses.search') }}</BaseButton>
                </form>
            </div>
            <OrnamentBand start="tree" />
        </section>

        <div class="mx-auto max-w-[1280px] px-6 py-10 md:px-8" aria-live="polite" :aria-busy="loading">
            <div v-if="loading && courses.length === 0" class="grid grid-cols-[repeat(auto-fill,minmax(300px,1fr))] gap-6" :aria-label="t('common.loading')">
                <div v-for="i in 3" :key="i" class="h-[360px] animate-pulse rounded-[20px] bg-lavender-50" />
            </div>

            <div v-else-if="error" role="alert" class="flex flex-col items-start gap-4 rounded-card border border-kalyna/30 bg-kalyna/5 p-6">
                <p class="m-0 text-kalyna">{{ error }}</p>
                <BaseButton variant="secondary" @click="load()">{{ t('common.retry') }}</BaseButton>
            </div>

            <div v-else-if="courses.length === 0" class="flex flex-col items-start gap-3 py-8">
                <h2 class="font-flair m-0 text-2xl font-bold text-plum">{{ t('courses.emptyTitle') }}</h2>
                <p class="m-0 text-ink-muted">{{ query ? t('courses.emptyFiltered') : t('courses.emptyAll') }}</p>
                <BaseButton v-if="query" variant="secondary" @click="query = ''; search()">{{ t('courses.clear') }}</BaseButton>
            </div>

            <template v-else>
                <p class="mt-0 mb-6 text-ink-muted">{{ plural(total, 'courses') }}</p>
                <div class="grid grid-cols-[repeat(auto-fill,minmax(300px,1fr))] gap-6">
                    <CourseCard v-for="c in courses" :key="c.uuid" :course="c" />
                </div>
                <div v-if="page < lastPage" class="mt-10 flex justify-center">
                    <BaseButton variant="secondary" :loading="loading" @click="load(page + 1)">{{ t('common.showMore') }}</BaseButton>
                </div>
            </template>
        </div>
    </div>
</template>
