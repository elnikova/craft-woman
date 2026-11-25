<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import BaseButton from '@/components/ui/BaseButton.vue';
import { coursesApi } from '@/api/courses';
import { toApiError } from '@/api/http';
import type { CourseDetail, LessonView } from '@/types/course';
import { formatFileSize } from '@/utils/format';

const props = defineProps<{ slug: string; lesson: string }>();
const { t } = useI18n();
const router = useRouter();

const course = ref<CourseDetail | null>(null);
const view = ref<LessonView | null>(null);
const loading = ref(true);
const forbidden = ref(false);
const error = ref('');
const busy = ref(false);
const actionError = ref('');
const outlineOpen = ref(false);

const enrollment = computed(() => course.value?.enrollment ?? null);
const isStudent = computed(() => !!enrollment.value?.has_access);
const isDone = (uuid: string) => enrollment.value?.completed_lessons.includes(uuid) ?? false;
const canOpen = (preview: boolean) => preview || isStudent.value || !!course.value?.is_owner;
const paragraphs = (text: string | null) => (text ?? '').split(/\n\s*\n/).filter((p) => p.trim());

async function load() {
    loading.value = true;
    forbidden.value = false;
    error.value = '';
    actionError.value = '';
    outlineOpen.value = false;
    try {
        // The outline only changes between courses, the lesson on every step
        if (!course.value || course.value.slug !== props.slug) course.value = await coursesApi.show(props.slug);
        view.value = await coursesApi.lesson(props.slug, props.lesson);
    } catch (e) {
        const err = toApiError(e);
        if (err.status === 403) forbidden.value = true;
        else error.value = err.message;
        view.value = null;
    } finally {
        loading.value = false;
    }
}

async function toggleDone() {
    if (!view.value || !course.value?.enrollment) return;
    busy.value = true;
    actionError.value = '';
    try {
        const p = view.value.completed
            ? await coursesApi.uncomplete(props.slug, view.value.uuid)
            : await coursesApi.complete(props.slug, view.value.uuid);
        view.value.completed = !view.value.completed;
        course.value.enrollment = { ...course.value.enrollment, ...p };
        // Move on after marking a lesson done
        if (view.value.completed && view.value.next) {
            await router.push({ name: 'course-lesson', params: { slug: props.slug, lesson: view.value.next.uuid } });
        }
    } catch (e) {
        actionError.value = toApiError(e).message;
    } finally {
        busy.value = false;
    }
}

watch(() => [props.slug, props.lesson], load, { immediate: true });
</script>

<template>
    <div class="mx-auto grid max-w-[1280px] gap-8 px-6 py-8 md:px-8 lg:grid-cols-[300px_minmax(0,1fr)]">
        <!-- Outline -->
        <aside class="lg:sticky lg:top-6 lg:self-start" :aria-label="t('lessonPage.program')">
            <RouterLink :to="{ name: 'course', params: { slug } }" class="text-[15px] text-plum">{{ t('lessonPage.toCourse') }}</RouterLink>
            <template v-if="course">
                <div class="mt-3 font-semibold text-plum" :lang="course.content_locale">{{ course.name }}</div>
                <div v-if="enrollment" class="mt-3">
                    <div class="mb-1 flex justify-between text-sm text-ink-muted">
                        <span>{{ t('coursePage.progress', { done: enrollment.completed, total: enrollment.total }) }}</span>
                        <span>{{ enrollment.percent }}%</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-lavender-100" role="progressbar" :aria-valuenow="enrollment.percent" aria-valuemin="0" aria-valuemax="100">
                        <div class="h-full rounded-full bg-plum" :style="{ width: `${enrollment.percent}%` }" />
                    </div>
                </div>
                <button
                    type="button"
                    class="mt-4 h-11 w-full rounded-full border border-line-strong text-[15px] text-plum lg:hidden"
                    :aria-expanded="outlineOpen"
                    aria-controls="lesson-outline"
                    @click="outlineOpen = !outlineOpen"
                >
                    {{ t('lessonPage.program') }}
                </button>
                <nav id="lesson-outline" class="mt-4 lg:block" :class="outlineOpen ? 'block' : 'hidden'">
                    <div v-for="m in course.modules" :key="m.uuid" class="mb-4">
                        <div class="mb-1.5 text-[13px] font-semibold text-ink-soft">{{ m.title }}</div>
                        <ul class="m-0 flex list-none flex-col gap-0.5 p-0">
                            <li v-for="l in m.lessons" :key="l.uuid">
                                <RouterLink
                                    v-if="canOpen(l.is_preview)"
                                    :to="{ name: 'course-lesson', params: { slug, lesson: l.uuid } }"
                                    class="flex items-start gap-2 rounded-[10px] px-2.5 py-2 text-[15px] no-underline"
                                    :class="l.uuid === lesson ? 'bg-plum text-white' : 'text-ink hover:bg-lavender-50'"
                                    :aria-current="l.uuid === lesson ? 'page' : undefined"
                                >
                                    <span aria-hidden="true" class="w-4 shrink-0">{{ isDone(l.uuid) ? '✓' : '' }}</span>
                                    <span>{{ l.title }}</span>
                                </RouterLink>
                                <span v-else class="flex items-start gap-2 px-2.5 py-2 text-[15px] text-ink-muted">
                                    <span aria-hidden="true" class="w-4 shrink-0">🔒</span><span>{{ l.title }}</span>
                                </span>
                            </li>
                        </ul>
                    </div>
                </nav>
            </template>
        </aside>

        <!-- Lesson -->
        <main class="min-w-0">
            <div v-if="loading" class="space-y-4" aria-busy="true" :aria-label="t('common.loading')">
                <div class="h-10 w-2/3 animate-pulse rounded bg-lavender-50" />
                <div class="aspect-video animate-pulse rounded-card bg-lavender-50" />
            </div>

            <div v-else-if="forbidden" class="flex flex-col items-start gap-4 rounded-card bg-lavender-50 p-8">
                <h1 class="font-flair m-0 text-3xl font-bold text-plum">{{ t('lessonPage.noAccessTitle') }}</h1>
                <p class="m-0 text-ink-muted">{{ t('lessonPage.noAccessText') }}</p>
                <BaseButton :to="{ name: 'course', params: { slug } }">{{ t('lessonPage.toCoursePage') }}</BaseButton>
            </div>

            <div v-else-if="error || !view" role="alert" class="flex flex-col items-start gap-4 rounded-card border border-kalyna/30 bg-kalyna/5 p-6">
                <p class="m-0 text-kalyna">{{ error }}</p>
                <BaseButton variant="secondary" @click="load">{{ t('common.retry') }}</BaseButton>
            </div>

            <article v-else>
                <div class="text-[15px] text-ink-muted">{{ view.module.title }}</div>
                <h1 class="font-flair mt-1 mb-0 text-[34px] leading-tight font-bold text-plum">{{ view.title }}</h1>
                <p v-if="view.description" class="mt-3 mb-0 text-lg text-ink-soft">{{ view.description }}</p>
                <p v-if="view.is_preview && !isStudent && !course?.is_owner" class="mt-4 mb-0 rounded-[10px] bg-notice-bg px-4 py-3 text-notice-ink">
                    {{ t('lessonPage.previewNote') }}
                    <RouterLink :to="{ name: 'course', params: { slug } }" class="font-semibold text-notice-ink">{{ t('lessonPage.toCoursePage') }}</RouterLink>
                </p>

                <div class="mt-8 flex flex-col gap-8">
                    <section v-for="m in view.materials" :key="m.uuid">
                        <h2 v-if="m.title && m.type !== 'link'" class="m-0 mb-3 text-xl font-semibold text-plum">{{ m.title }}</h2>
                        <div v-if="m.type === 'text'" class="flex max-w-[70ch] flex-col gap-4 text-[17px] leading-relaxed text-ink">
                            <p v-for="(p, i) in paragraphs(m.content)" :key="i" class="m-0 whitespace-pre-line">{{ p }}</p>
                        </div>
                        <div v-else-if="m.type === 'video' && m.embed_url" class="aspect-video overflow-hidden rounded-card bg-ink">
                            <iframe
                                :src="m.embed_url"
                                :title="t('lessonPage.video', { title: m.title || view.title })"
                                class="h-full w-full border-0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                                allowfullscreen
                                loading="lazy"
                                referrerpolicy="strict-origin-when-cross-origin"
                            />
                        </div>
                        <a
                            v-else-if="m.type === 'file' && m.file"
                            :href="coursesApi.fileUrl(slug, view.uuid, m.uuid)"
                            class="inline-flex h-12 items-center gap-2 rounded-full bg-lavender-50 px-5 font-semibold text-plum no-underline hover:bg-lavender-100"
                            download
                        >
                            {{ t('lessonPage.download', { name: m.file.name }) }}
                            <span class="font-normal text-ink-muted">{{ formatFileSize(m.file.size) }}</span>
                        </a>
                        <a
                            v-else-if="m.type === 'link' && m.url"
                            :href="m.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex h-12 items-center gap-2 rounded-full border-[1.5px] border-plum px-5 font-semibold text-plum no-underline hover:bg-lavender-50"
                        >
                            {{ m.title || t('lessonPage.openLink') }} ↗
                        </a>
                    </section>
                </div>

                <div class="mt-10 flex flex-wrap items-center gap-3 border-t border-line pt-6">
                    <template v-if="isStudent">
                        <BaseButton v-if="!view.completed" :loading="busy" @click="toggleDone">{{ t('lessonPage.markDone') }}</BaseButton>
                        <template v-else>
                            <span class="inline-flex h-11 items-center rounded-full bg-[#E6F0DC] px-5 font-semibold text-[#2F4A1C]">✓ {{ t('lessonPage.done') }}</span>
                            <BaseButton variant="ghost" :loading="busy" @click="toggleDone">{{ t('lessonPage.undo') }}</BaseButton>
                        </template>
                    </template>
                    <span class="flex-1" />
                    <BaseButton v-if="view.previous" variant="ghost" :to="{ name: 'course-lesson', params: { slug, lesson: view.previous.uuid } }">
                        {{ t('lessonPage.previous', { title: view.previous.title }) }}
                    </BaseButton>
                    <BaseButton v-if="view.next" variant="secondary" :to="{ name: 'course-lesson', params: { slug, lesson: view.next.uuid } }">
                        {{ t('lessonPage.next', { title: view.next.title }) }}
                    </BaseButton>
                </div>
                <p v-if="actionError" role="alert" class="mt-3 mb-0 text-kalyna">{{ actionError }}</p>
                <div v-if="enrollment?.completed_at" role="status" class="mt-6 flex flex-wrap items-center gap-3 rounded-card bg-[#E6F0DC] p-5 text-[#2F4A1C]">
                    <span class="font-semibold">{{ t('lessonPage.courseDone') }}</span>
                    <BaseButton v-if="course?.certificate_enabled" variant="secondary" :to="{ name: 'course', params: { slug } }">
                        {{ enrollment.certificate_number ? t('coursePage.viewCertificate') : t('coursePage.getCertificate') }}
                    </BaseButton>
                </div>
            </article>
        </main>
    </div>
</template>
