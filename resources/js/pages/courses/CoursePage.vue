<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import BaseButton from '@/components/ui/BaseButton.vue';
import MakerAvatar from '@/components/makers/MakerAvatar.vue';
import ProductCover from '@/components/products/ProductCover.vue';
import ProductStatusBadge from '@/components/products/ProductStatusBadge.vue';
import { coursesApi } from '@/api/courses';
import { toApiError } from '@/api/http';
import { useCartStore } from '@/stores/cart';
import type { CourseDetail } from '@/types/course';
import { accessLabel } from '@/utils/course';
import { formatPrice } from '@/utils/format';
import { formatDate } from '@/utils/orderStatus';

const props = defineProps<{ slug: string }>();
const { t, locale } = useI18n();
const router = useRouter();
const cart = useCartStore();

const course = ref<CourseDetail | null>(null);
const loading = ref(true);
const notFound = ref(false);
const error = ref('');
const adding = ref(false);
const added = ref(false);
const actionError = ref('');
const issuing = ref(false);

const enrollment = computed(() => course.value?.enrollment ?? null);
const inCart = computed(() => cart.cart.groups.some((g) => g.items.some((i) => i.kind === 'course' && i.product_uuid === course.value?.uuid)));
const paragraphs = computed(() => (course.value?.description ?? '').split(/\n\s*\n/).filter((p) => p.trim()));
const allLessons = computed(() => course.value?.modules.flatMap((m) => m.lessons) ?? []);
const isDone = (uuid: string) => enrollment.value?.completed_lessons.includes(uuid) ?? false;

/** First lesson not done yet, or the first lesson */
const nextLesson = computed(() => allLessons.value.find((l) => !isDone(l.uuid)) ?? allLessons.value[0]);
const canOpen = (lessonPreview: boolean) => lessonPreview || !!enrollment.value?.has_access || !!course.value?.is_owner;

async function load() {
    loading.value = true;
    notFound.value = false;
    error.value = '';
    try {
        course.value = await coursesApi.show(props.slug);
    } catch (e) {
        const err = toApiError(e);
        if (err.status === 404) notFound.value = true;
        else error.value = err.message;
    } finally {
        loading.value = false;
    }
}

async function addToCart() {
    if (!course.value) return;
    adding.value = true;
    actionError.value = '';
    try {
        await cart.addCourse(course.value.uuid);
        added.value = true;
    } catch (e) {
        actionError.value = toApiError(e).message;
    } finally {
        adding.value = false;
    }
}

async function certificate() {
    if (!course.value) return;
    issuing.value = true;
    actionError.value = '';
    try {
        const cert = await coursesApi.certificate(course.value.slug);
        await router.push({ name: 'certificate', params: { number: cert.number } });
    } catch (e) {
        actionError.value = toApiError(e).message;
    } finally {
        issuing.value = false;
    }
}

watch(() => props.slug, load, { immediate: true });
</script>

<template>
    <div v-if="loading" class="mx-auto grid max-w-[1280px] animate-pulse gap-10 px-6 py-12 md:grid-cols-2 md:px-8" aria-busy="true" :aria-label="t('common.loading')">
        <div class="aspect-video rounded-card bg-lavender-50" />
        <div class="space-y-4"><div class="h-12 rounded bg-lavender-50" /><div class="h-6 w-1/2 rounded bg-lavender-50" /></div>
    </div>

    <div v-else-if="notFound" class="mx-auto flex max-w-[640px] flex-col items-start gap-4 px-6 py-20">
        <h1 class="font-flair m-0 text-4xl font-bold text-plum">{{ t('coursePage.notFoundTitle') }}</h1>
        <p class="m-0 text-lg text-ink-muted">{{ t('coursePage.notFoundText') }}</p>
        <BaseButton :to="{ name: 'courses' }">{{ t('coursePage.allCourses') }}</BaseButton>
    </div>

    <div v-else-if="error || !course" role="alert" class="mx-auto flex max-w-[640px] flex-col items-start gap-4 px-6 py-20">
        <p class="m-0 text-kalyna">{{ error }}</p>
        <BaseButton variant="secondary" @click="load">{{ t('common.retry') }}</BaseButton>
    </div>

    <template v-else>
        <div v-if="course.status !== 'published'" class="bg-notice-bg">
            <div class="mx-auto flex max-w-[1280px] flex-wrap items-center gap-3 px-6 py-3 text-notice-ink md:px-8">
                <ProductStatusBadge :status="course.status" />
                <span>{{ t('coursePage.unpublished') }}</span>
            </div>
        </div>

        <section class="tx-weave bg-lavender-50">
            <div class="mx-auto grid max-w-[1280px] items-center gap-10 px-6 py-12 md:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)] md:px-8">
                <ProductCover :uuid="course.uuid" :name="course.name" :src="course.cover_url" ratio="wide" />
                <div class="flex flex-col gap-4">
                    <RouterLink :to="{ name: 'courses' }" class="text-[15px] text-plum">{{ t('coursePage.allCourses') }}</RouterLink>
                    <h1 class="font-flair m-0 text-[36px] leading-tight font-bold tracking-[-0.015em] text-plum md:text-[44px]" :lang="course.content_locale">
                        {{ course.name }}
                    </h1>
                    <div class="flex flex-wrap gap-x-5 gap-y-1 text-[15px] text-ink-soft">
                        <span>{{ t('coursePage.modules', { n: course.modules_count }) }}</span>
                        <span>{{ t('coursePage.lessons', { n: course.lessons_count }) }}</span>
                        <span>{{ accessLabel(course) }}</span>
                        <span>{{ course.certificate_enabled ? t('courses.certificate') : t('courses.noCertificate') }}</span>
                    </div>

                    <!-- Owner -->
                    <div v-if="course.is_owner" class="mt-2 flex flex-wrap gap-3">
                        <BaseButton :to="{ name: 'studio-course', params: { uuid: course.uuid } }">{{ t('coursePage.edit') }}</BaseButton>
                    </div>

                    <!-- Student with access -->
                    <div v-else-if="enrollment?.has_access" class="mt-2 flex flex-col gap-3">
                        <div>
                            <div class="mb-1.5 flex justify-between text-[15px] text-ink">
                                <span>{{ t('coursePage.progress', { done: enrollment.completed, total: enrollment.total }) }}</span>
                                <span class="font-semibold">{{ enrollment.percent }}%</span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-white" role="progressbar" :aria-valuenow="enrollment.percent" aria-valuemin="0" aria-valuemax="100">
                                <div class="h-full rounded-full bg-plum" :style="{ width: `${enrollment.percent}%` }" />
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <BaseButton v-if="nextLesson" size="lg" :to="{ name: 'course-lesson', params: { slug: course.slug, lesson: nextLesson.uuid } }">
                                {{ enrollment.completed ? t('coursePage.continue') : t('coursePage.start') }}
                            </BaseButton>
                            <template v-if="enrollment.completed_at && course.certificate_enabled">
                                <BaseButton
                                    v-if="enrollment.certificate_number"
                                    variant="secondary"
                                    :to="{ name: 'certificate', params: { number: enrollment.certificate_number } }"
                                >
                                    {{ t('coursePage.viewCertificate') }}
                                </BaseButton>
                                <BaseButton v-else variant="secondary" :loading="issuing" @click="certificate">{{ t('coursePage.getCertificate') }}</BaseButton>
                            </template>
                        </div>
                        <p v-if="enrollment.expires_at" class="m-0 text-sm text-ink-muted">{{ t('coursePage.expiresOn', { date: formatDate(enrollment.expires_at) }) }}</p>
                    </div>

                    <!-- Buyer -->
                    <div v-else class="mt-2 flex flex-col gap-3">
                        <p v-if="enrollment && !enrollment.has_access" class="m-0 rounded-[10px] bg-notice-bg px-4 py-3 text-[15px] text-notice-ink">
                            {{ t('coursePage.expired', { date: formatDate(enrollment.expires_at) }) }}
                        </p>
                        <div class="text-[32px] font-bold text-plum">{{ formatPrice(course.price.amount_minor, course.price.currency) }}</div>
                        <div class="flex flex-wrap items-center gap-3">
                            <BaseButton v-if="!inCart" size="lg" :loading="adding" @click="addToCart">
                                {{ enrollment ? t('coursePage.buyAgain') : t('coursePage.addToCart') }}
                            </BaseButton>
                            <BaseButton v-else size="lg" variant="secondary" :to="{ name: 'cart' }">{{ t('coursePage.toCart') }}</BaseButton>
                        </div>
                        <p v-if="added" role="status" class="m-0 text-[15px]">{{ t('coursePage.inCart') }}</p>
                    </div>
                    <p v-if="actionError" role="alert" class="m-0 text-[15px] text-kalyna">{{ actionError }}</p>
                </div>
            </div>
        </section>

        <div class="mx-auto grid max-w-[1280px] gap-14 px-6 py-14 md:px-8 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
            <section aria-labelledby="program-title">
                <h2 id="program-title" class="font-flair m-0 text-3xl font-bold text-plum">{{ t('coursePage.program') }}</h2>
                <ol class="mt-6 mb-0 flex list-none flex-col gap-4 p-0">
                    <li v-for="(m, mi) in course.modules" :key="m.uuid" class="rounded-card border border-line bg-white">
                        <div class="border-b border-line px-5 py-3.5">
                            <div class="text-sm text-ink-muted">{{ mi + 1 }}</div>
                            <div class="text-lg font-semibold text-plum">{{ m.title }}</div>
                            <p v-if="m.description" class="mt-1 mb-0 text-[15px] text-ink-muted">{{ m.description }}</p>
                        </div>
                        <ol class="m-0 list-none p-0">
                            <li v-for="l in m.lessons" :key="l.uuid" class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-3 last:border-b-0">
                                <span
                                    class="grid h-6 w-6 shrink-0 place-items-center rounded-full border text-[12px]"
                                    :class="isDone(l.uuid) ? 'border-plum bg-plum text-white' : 'border-line-strong text-transparent'"
                                    aria-hidden="true"
                                    >✓</span
                                >
                                <RouterLink
                                    v-if="canOpen(l.is_preview)"
                                    :to="{ name: 'course-lesson', params: { slug: course.slug, lesson: l.uuid } }"
                                    class="min-w-0 flex-1 font-medium text-ink no-underline hover:text-plum hover:underline"
                                >
                                    {{ l.title }}
                                </RouterLink>
                                <span v-else class="min-w-0 flex-1 text-ink">{{ l.title }}</span>
                                <span class="text-[13px] text-ink-muted">
                                    {{ l.material_types.map((type) => t(`coursePage.materials.${type}`)).join(', ') }}
                                    <template v-if="!l.is_required"> · {{ t('coursePage.optional') }}</template>
                                    <template v-if="isDone(l.uuid)"> · <span class="sr-only">{{ t('coursePage.done') }}</span></template>
                                </span>
                                <RouterLink
                                    v-if="l.is_preview && !enrollment?.has_access && !course.is_owner"
                                    :to="{ name: 'course-lesson', params: { slug: course.slug, lesson: l.uuid } }"
                                    class="rounded-full bg-lavender-50 px-3 py-1 text-[13px] font-semibold text-plum no-underline"
                                >
                                    {{ t('coursePage.preview') }}
                                </RouterLink>
                            </li>
                        </ol>
                    </li>
                </ol>
            </section>

            <aside class="flex flex-col gap-8">
                <section v-if="paragraphs.length" aria-labelledby="about-title">
                    <h2 id="about-title" class="font-flair m-0 text-3xl font-bold text-plum">{{ t('coursePage.about') }}</h2>
                    <p v-if="course.content_locale !== locale" class="mt-3 mb-0 text-sm text-ink-muted">{{ t('lang.contentNote') }}</p>
                    <div class="mt-4 flex flex-col gap-3 text-[17px] leading-relaxed text-ink" :lang="course.content_locale">
                        <p v-for="(p, i) in paragraphs" :key="i" class="m-0">{{ p }}</p>
                    </div>
                </section>
                <RouterLink
                    v-if="course.maker"
                    :to="{ name: 'maker', params: { slug: course.maker.slug } }"
                    class="flex items-center gap-4 rounded-card bg-lavender-50 p-4 text-inherit no-underline hover:bg-lavender-100"
                >
                    <MakerAvatar :uuid="course.maker.uuid" :name="course.maker.display_name" :src="course.maker.avatar_url" shape="circle" class="w-14 shrink-0" />
                    <div>
                        <div class="text-sm text-ink-muted">{{ t('coursePage.maker') }}</div>
                        <div class="font-semibold text-plum">{{ course.maker.display_name }}</div>
                        <div v-if="course.maker.city" class="text-sm text-ink-muted">{{ course.maker.city }}</div>
                    </div>
                </RouterLink>
            </aside>
        </div>
    </template>
</template>
