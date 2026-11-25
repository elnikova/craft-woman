<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { onBeforeRouteLeave, useRouter } from 'vue-router';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseInput from '@/components/ui/BaseInput.vue';
import BaseTextarea from '@/components/ui/BaseTextarea.vue';
import ContentLanguage from '@/components/ui/ContentLanguage.vue';
import ProductCover from '@/components/products/ProductCover.vue';
import ProductStatusBadge from '@/components/products/ProductStatusBadge.vue';
import { coursesApi } from '@/api/courses';
import { taxonomyApi } from '@/api/taxonomy';
import { toApiError, type FieldErrors } from '@/api/http';
import type { Locale } from '@/i18n';
import type { AccessType, CourseEditor, CoursePayload, EditorLesson, MaterialType } from '@/types/course';
import type { Currency } from '@/types/product';
import type { Category } from '@/types/taxonomy';
import { formatFileSize, inputToMinor, minorToInput } from '@/utils/format';

const props = defineProps<{ uuid?: string }>();
const router = useRouter();
const { t } = useI18n();

const MATERIAL_TYPES: MaterialType[] = ['text', 'video', 'file', 'link'];

const course = ref<CourseEditor | null>(null);
const categories = ref<Category[]>([]);
const loading = ref(true);
const loadError = ref('');

const form = reactive({
    name: '',
    description: '',
    price: '',
    currency: 'EUR' as Currency,
    category_uuid: '',
    access_type: 'lifetime' as AccessType,
    access_days: '',
    certificate_enabled: true,
    content_locale: 'uk' as Locale,
    tr_name: '',
    tr_description: '',
});
const otherLocale = computed<Locale>(() => (form.content_locale === 'uk' ? 'en' : 'uk'));
const saved = ref('');
const dirty = computed(() => JSON.stringify(form) !== saved.value);

const errors = ref<FieldErrors>({});
const formError = ref('');
const notice = ref('');
const busy = ref<string | null>(null);
const confirmDelete = ref(false);

const isNew = computed(() => !course.value);
const status = computed(() => course.value?.status ?? 'draft');
const editable = computed(() => isNew.value || course.value?.is_editable === true);

function fill(c: CourseEditor, resetForm = true) {
    course.value = c;
    if (!resetForm) return;
    const other = c.content_locale === 'en' ? 'uk' : 'en';
    Object.assign(form, {
        name: c.name,
        description: c.description ?? '',
        price: minorToInput(c.price.amount_minor || null),
        currency: c.price.currency,
        category_uuid: c.category?.uuid ?? '',
        access_type: c.access_type,
        access_days: c.access_days?.toString() ?? '',
        certificate_enabled: c.certificate_enabled,
        content_locale: c.content_locale ?? 'uk',
        tr_name: c.translations?.[other]?.name ?? '',
        tr_description: c.translations?.[other]?.description ?? '',
    });
    saved.value = JSON.stringify(form);
}

function payload(): CoursePayload | null {
    const price = inputToMinor(form.price);
    if (Number.isNaN(price)) {
        errors.value = { price_amount_minor: t('editor.priceFormat') };
        formError.value = t('editor.checkFields');
        return null;
    }
    return {
        name: form.name.trim(),
        description: form.description.trim() || null,
        price_amount_minor: price ?? 0,
        currency: form.currency,
        access_type: form.access_type,
        access_days: form.access_type === 'limited' && form.access_days.trim() ? Number(form.access_days) : null,
        certificate_enabled: form.certificate_enabled,
        category_uuid: form.category_uuid || null,
        content_locale: form.content_locale,
        translations: { [otherLocale.value]: { name: form.tr_name.trim() || null, description: form.tr_description.trim() || null } },
    };
}

async function load() {
    loading.value = true;
    loadError.value = '';
    try {
        const [cats, c] = await Promise.all([taxonomyApi.categories(), props.uuid ? coursesApi.mineOne(props.uuid) : Promise.resolve(null)]);
        categories.value = cats;
        if (c) fill(c);
        else saved.value = JSON.stringify(form);
    } catch (e) {
        loadError.value = toApiError(e).message;
    } finally {
        loading.value = false;
    }
}

async function run<T>(key: string, fn: () => Promise<T>, success?: string): Promise<T | null> {
    busy.value = key;
    formError.value = '';
    notice.value = '';
    try {
        const result = await fn();
        if (success) notice.value = success;
        return result;
    } catch (e) {
        const err = toApiError(e);
        errors.value = err.fields;
        formError.value = Object.keys(err.fields).length ? t('editor.checkFields') : err.message;
        return null;
    } finally {
        busy.value = null;
    }
}

async function save(): Promise<boolean> {
    errors.value = {};
    const body = payload();
    if (!body) return false;
    const wasNew = isNew.value;
    const c = await run('save', () => (course.value ? coursesApi.update(course.value.uuid, body) : coursesApi.create(body)), t('courseEditor.saved'));
    if (!c) return false;
    fill(c);
    if (wasNew) {
        notice.value = t('courseEditor.created');
        await router.replace({ name: 'studio-course', params: { uuid: c.uuid } });
    }
    return true;
}

async function submit() {
    if ((dirty.value || isNew.value) && !(await save())) return;
    const c = await run('submit', () => coursesApi.submit(course.value!.uuid), t('courseEditor.submitted'));
    if (c) fill(c);
}

async function lifecycle(action: 'archive' | 'relist') {
    const c = await run(action, () => coursesApi[action](course.value!.uuid), action === 'archive' ? t('courseEditor.archived') : t('courseEditor.relisted'));
    if (c) fill(c);
}

async function destroy() {
    const ok = await run('delete', () => coursesApi.remove(course.value!.uuid));
    if (ok !== null) {
        saved.value = JSON.stringify(form);
        await router.push({ name: 'studio-courses' });
    }
}

/** Curriculum changes are saved at once and must not reset unsaved details */
async function curriculum(key: string, fn: () => Promise<CourseEditor>) {
    const c = await run(key, fn);
    if (c) fill(c, false);
    return c;
}

// ---- cover ----
async function onCover(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    (e.target as HTMLInputElement).value = '';
    if (file) await curriculum('cover', () => coursesApi.cover(course.value!.uuid, file));
}

// ---- modules ----
const newModule = ref('');
async function addModule() {
    if (!newModule.value.trim()) return;
    if (await curriculum('module', () => coursesApi.addModule(course.value!.uuid, newModule.value.trim()))) newModule.value = '';
}
function renameModule(uuid: string, e: Event) {
    const title = (e.target as HTMLInputElement).value.trim();
    if (title) void curriculum(`m-${uuid}`, () => coursesApi.updateModule(uuid, { title }));
}
function moveModule(i: number, dir: -1 | 1) {
    const order = course.value!.modules.map((m) => m.uuid);
    const [m] = order.splice(i, 1);
    order.splice(i + dir, 0, m!);
    void curriculum('order', () => coursesApi.reorderModules(course.value!.uuid, order));
}
function deleteModule(uuid: string, title: string) {
    if (window.confirm(t('courseEditor.deleteModuleConfirm', { title }))) void curriculum(`m-${uuid}`, () => coursesApi.deleteModule(uuid));
}

// ---- lessons ----
const newLesson = reactive<Record<string, string>>({});
async function addLesson(moduleUuid: string) {
    const title = newLesson[moduleUuid]?.trim();
    if (!title) return;
    if (await curriculum(`add-${moduleUuid}`, () => coursesApi.addLesson(moduleUuid, title))) newLesson[moduleUuid] = '';
}
function updateLesson(l: EditorLesson, patch: { title?: string; is_required?: boolean; is_preview?: boolean }) {
    if (patch.title !== undefined && !patch.title.trim()) return;
    void curriculum(`l-${l.uuid}`, () => coursesApi.updateLesson(l.uuid, patch));
}
function moveLesson(moduleUuid: string, lessons: EditorLesson[], i: number, dir: -1 | 1) {
    const order = lessons.map((l) => l.uuid);
    const [l] = order.splice(i, 1);
    order.splice(i + dir, 0, l!);
    void curriculum('order', () => coursesApi.reorderLessons(moduleUuid, order));
}
function deleteLesson(l: EditorLesson) {
    if (window.confirm(t('courseEditor.deleteLessonConfirm', { title: l.title }))) void curriculum(`l-${l.uuid}`, () => coursesApi.deleteLesson(l.uuid));
}

// ---- materials ----
const draft = reactive({ lesson: null as string | null, type: 'text' as MaterialType, title: '', content: '', url: '', file: null as File | null });
const draftError = ref('');
const uploadProgress = ref<number | null>(null);

async function openMaterial(lesson: string) {
    Object.assign(draft, { lesson, type: 'text', title: '', content: '', url: '', file: null });
    draftError.value = '';
    await nextTick();
    document.getElementById(`mat-${lesson}`)?.querySelector<HTMLElement>('input[type=radio]:checked')?.focus();
}

async function addMaterial() {
    if (!draft.lesson) return;
    draftError.value = '';
    const lesson = draft.lesson;
    uploadProgress.value = draft.type === 'file' ? 0 : null;
    const c = await curriculum(`mat-${lesson}`, () =>
        coursesApi.addMaterial(
            lesson,
            {
                type: draft.type,
                title: draft.title.trim() || null,
                content: draft.type === 'text' ? draft.content : null,
                external_url: draft.type === 'video' || draft.type === 'link' ? draft.url.trim() : null,
                file: draft.type === 'file' ? draft.file : null,
            },
            (pct) => (uploadProgress.value = pct),
        ),
    );
    uploadProgress.value = null;
    if (c) draft.lesson = null;
    else draftError.value = Object.values(errors.value)[0] ?? formError.value;
    formError.value = '';
}

function deleteMaterial(uuid: string) {
    void curriculum(`mat-${uuid}`, () => coursesApi.deleteMaterial(uuid));
}

function materialSummary(m: EditorLesson['materials'][number]): string {
    if (m.type === 'text') return (m.content ?? '').slice(0, 90) + ((m.content?.length ?? 0) > 90 ? '…' : '');
    if (m.type === 'file') return m.file ? `${m.file.name} · ${formatFileSize(m.file.size)}` : '';
    return m.url ?? '';
}

watch(() => props.uuid, (u) => { if (u && u !== course.value?.uuid) void load(); });

function beforeUnload(e: BeforeUnloadEvent) {
    if (dirty.value) e.preventDefault();
}
onMounted(() => {
    void load();
    window.addEventListener('beforeunload', beforeUnload);
});
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));
onBeforeRouteLeave((to) => (dirty.value && to.name !== 'studio-course' ? window.confirm(t('courseEditor.leaveConfirm')) : true));

const selectClass = 'h-[52px] rounded-control border-[1.5px] border-line-strong bg-white px-4 text-base text-ink focus:border-plum focus:outline-none disabled:bg-lavender-50';
const smallBtn = 'h-9 rounded-full border border-line-strong px-3 text-sm text-plum hover:border-plum disabled:opacity-40';
</script>

<template>
    <div class="max-w-[920px]">
        <RouterLink :to="{ name: 'studio-courses' }" class="text-[15px] text-plum">{{ t('courseEditor.all') }}</RouterLink>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-4">
            <h1 class="font-flair m-0 text-[36px] leading-tight font-bold tracking-[-0.015em] text-plum">{{ isNew ? t('courseEditor.new') : form.name || t('courseEditor.new') }}</h1>
            <ProductStatusBadge v-if="course?.status" :status="course.status" />
        </div>

        <div v-if="loading" class="mt-8 space-y-4" aria-busy="true" :aria-label="t('common.loading')">
            <div v-for="i in 4" :key="i" class="h-14 animate-pulse rounded-control bg-lavender-50" />
        </div>

        <div v-else-if="loadError" role="alert" class="mt-8 flex flex-col items-start gap-4 rounded-card border border-kalyna/30 bg-kalyna/5 p-6">
            <p class="m-0 text-kalyna">{{ loadError }}</p>
            <BaseButton variant="secondary" @click="load">{{ t('common.retry') }}</BaseButton>
        </div>

        <template v-else>
            <!-- What is happening with the course -->
            <div v-if="course" class="mt-6 flex flex-col gap-2 rounded-card p-5" :class="status === 'rejected' ? 'bg-kalyna/5' : 'bg-lavender-50'">
                <p class="m-0" :class="status === 'rejected' ? 'font-semibold text-kalyna' : 'text-ink'">{{ t(`courseEditor.${{ draft: 'draftHint', pending_review: 'pendingHint', published: 'publishedHint', rejected: 'rejectedHint', archived: 'archivedHint' }[status]}`) }}</p>
                <p v-if="course.review_note && (status === 'rejected' || status === 'archived')" class="m-0 text-ink">
                    <span class="font-semibold">{{ t('courseEditor.moderatorNote') }}</span> {{ course.review_note }}
                </p>
                <div v-if="course.missing.length && (status === 'draft' || status === 'rejected')">
                    <p class="m-0 font-semibold text-ink">{{ t('courseEditor.missingTitle') }}</p>
                    <ul class="mt-1 mb-0 pl-5 text-ink">
                        <li v-for="key in course.missing" :key="key">{{ t(`courseEditor.req.${key}`) }}</li>
                    </ul>
                </div>
                <div class="flex flex-wrap gap-x-5 gap-y-1 text-[15px]">
                    <RouterLink :to="{ name: 'course', params: { slug: course.slug } }" class="font-semibold text-plum">{{ t('courseEditor.previewPage') }}</RouterLink>
                    <span v-if="course.students_count" class="text-ink-muted">{{ t('courseEditor.students', { n: course.students_count }) }}</span>
                </div>
            </div>

            <form class="mt-8 flex flex-col gap-10" novalidate @submit.prevent="save">
                <fieldset :disabled="!editable" class="m-0 flex flex-col gap-10 border-0 p-0">
                    <section class="flex flex-col gap-6" aria-labelledby="sec-details">
                        <h2 id="sec-details" class="m-0 text-xl font-semibold text-plum">{{ t('courseEditor.details') }}</h2>
                        <BaseInput v-model="form.name" :label="t('courseEditor.name')" :maxlength="160" required :error="errors.name" />
                        <BaseTextarea v-model="form.description" :label="t('courseEditor.description')" :rows="6" :maxlength="10000" :hint="t('courseEditor.descriptionHint')" :error="errors.description" />

                        <ContentLanguage v-model="form.content_locale" />
                        <fieldset class="m-0 flex flex-col gap-5 rounded-card border border-line p-5" :lang="otherLocale">
                            <legend class="px-1 text-[15px] font-semibold text-plum">{{ t('content.translation', { lang: t(`content.langName.${otherLocale}`) }) }}</legend>
                            <p class="m-0 text-[13px] text-ink-muted">{{ t('content.translationHint') }}</p>
                            <BaseInput v-model="form.tr_name" :label="t('courseEditor.name')" :maxlength="160" :error="errors[`translations.${otherLocale}.name`]" />
                            <BaseTextarea v-model="form.tr_description" :label="t('courseEditor.description')" :rows="5" :maxlength="10000" :error="errors[`translations.${otherLocale}.description`]" />
                        </fieldset>

                        <div class="flex flex-col gap-2 md:max-w-[420px]">
                            <label for="category" class="text-[15px] font-semibold text-ink">{{ t('courseEditor.category') }}</label>
                            <select id="category" v-model="form.category_uuid" :class="selectClass">
                                <option value="">{{ t('courseEditor.chooseCategory') }}</option>
                                <optgroup v-for="c in categories" :key="c.uuid" :label="c.name">
                                    <option :value="c.uuid">{{ t('editor.general', { name: c.name }) }}</option>
                                    <option v-for="ch in c.children" :key="ch.uuid" :value="ch.uuid">{{ ch.name }}</option>
                                </optgroup>
                            </select>
                            <p v-if="errors.category_uuid" class="m-0 text-sm font-medium text-kalyna">{{ errors.category_uuid }}</p>
                        </div>
                    </section>

                    <section class="flex flex-col gap-6" aria-labelledby="sec-price">
                        <h2 id="sec-price" class="m-0 text-xl font-semibold text-plum">{{ t('courseEditor.priceAccess') }}</h2>
                        <div class="grid max-w-[480px] grid-cols-[minmax(0,1fr)_120px] gap-4">
                            <BaseInput v-model="form.price" :label="t('courseEditor.price')" placeholder="45,00" :error="errors.price_amount_minor" />
                            <div class="flex flex-col gap-2">
                                <label for="currency" class="text-[15px] font-semibold text-ink">{{ t('courseEditor.currency') }}</label>
                                <select id="currency" v-model="form.currency" :class="selectClass">
                                    <option value="EUR">EUR</option>
                                    <option value="USD">USD</option>
                                    <option value="UAH">UAH</option>
                                </select>
                            </div>
                        </div>
                        <fieldset class="m-0 flex flex-col gap-3 border-0 p-0">
                            <legend class="mb-2 text-[15px] font-semibold text-ink">{{ t('courseEditor.access') }}</legend>
                            <div class="flex flex-wrap gap-3">
                                <label v-for="opt in (['lifetime', 'limited'] as const)" :key="opt" class="flex cursor-pointer items-center gap-2 rounded-control border-2 px-4 py-3" :class="form.access_type === opt ? 'border-plum bg-lavender-50' : 'border-line'">
                                    <input v-model="form.access_type" type="radio" :value="opt" class="h-5 w-5 accent-plum" />
                                    {{ t(`courseEditor.${opt}`) }}
                                </label>
                            </div>
                            <div v-if="form.access_type === 'limited'" class="max-w-[220px]">
                                <BaseInput v-model="form.access_days" :label="t('courseEditor.accessDays')" :error="errors.access_days" />
                            </div>
                        </fieldset>
                        <label class="flex cursor-pointer items-start gap-3">
                            <input v-model="form.certificate_enabled" type="checkbox" class="mt-0.5 h-5 w-5 accent-plum" />
                            <span class="text-ink">{{ t('courseEditor.certificate') }}</span>
                        </label>
                    </section>
                </fieldset>

                <div v-if="formError" role="alert" class="rounded-[10px] border border-kalyna/30 bg-kalyna/5 px-3.5 py-3 text-[15px] text-kalyna">{{ formError }}</div>
                <p v-else-if="notice" role="status" class="m-0 text-[15px] font-semibold text-[#2F4A1C]">{{ notice }}</p>

                <div class="flex flex-wrap gap-3 border-t border-line pt-6">
                    <template v-if="editable">
                        <BaseButton type="submit" size="lg" :variant="status === 'draft' || status === 'rejected' ? 'secondary' : 'primary'" :loading="busy === 'save'" :disabled="!dirty && !isNew">
                            {{ isNew ? t('courseEditor.createDraft') : t('courseEditor.save') }}
                        </BaseButton>
                        <BaseButton v-if="!isNew && (status === 'draft' || status === 'rejected')" size="lg" :loading="busy === 'submit'" @click="submit">{{ t('courseEditor.submit') }}</BaseButton>
                    </template>
                    <BaseButton v-if="status === 'published' && course" variant="ghost" :loading="busy === 'archive'" @click="lifecycle('archive')">{{ t('courseEditor.archive') }}</BaseButton>
                    <BaseButton v-if="status === 'archived'" :loading="busy === 'relist'" @click="lifecycle('relist')">{{ t('courseEditor.relist') }}</BaseButton>
                    <template v-if="course?.is_deletable">
                        <span class="flex-1" />
                        <template v-if="confirmDelete">
                            <span class="self-center text-[15px] text-ink">{{ t('courseEditor.deleteConfirm') }}</span>
                            <BaseButton variant="secondary" :loading="busy === 'delete'" @click="destroy">{{ t('editor.yesDelete') }}</BaseButton>
                            <BaseButton variant="ghost" @click="confirmDelete = false">{{ t('editor.no') }}</BaseButton>
                        </template>
                        <BaseButton v-else variant="ghost" @click="confirmDelete = true">{{ t('courseEditor.deleteDraft') }}</BaseButton>
                    </template>
                </div>
            </form>

            <p v-if="isNew" class="mt-10 mb-0 rounded-[10px] bg-lavender-50 px-4 py-3 text-[15px] text-plum">{{ t('courseEditor.saveFirst') }}</p>

            <template v-else-if="course">
                <!-- Cover -->
                <section class="mt-12 flex flex-col gap-4" aria-labelledby="sec-cover">
                    <div>
                        <h2 id="sec-cover" class="m-0 text-xl font-semibold text-plum">{{ t('courseEditor.cover') }}</h2>
                        <p class="mt-1 mb-0 text-[15px] text-ink-muted">{{ t('courseEditor.coverHint') }}</p>
                    </div>
                    <ProductCover :uuid="course.uuid" :name="course.name" :src="course.cover_url" ratio="wide" class="max-w-[420px]" />
                    <label v-if="editable" class="flex h-[52px] w-fit cursor-pointer items-center rounded-full border-[1.5px] border-plum px-5 font-semibold text-plum hover:bg-lavender-50">
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" :disabled="!!busy" @change="onCover" />
                        {{ t('courseEditor.uploadCover') }}
                    </label>
                </section>

                <!-- Curriculum -->
                <section class="mt-12 flex flex-col gap-5" aria-labelledby="sec-curriculum">
                    <div>
                        <h2 id="sec-curriculum" class="m-0 text-xl font-semibold text-plum">{{ t('courseEditor.curriculum') }}</h2>
                        <p class="mt-1 mb-0 text-[15px] text-ink-muted">{{ t('courseEditor.curriculumHint') }}</p>
                    </div>

                    <ol class="m-0 flex list-none flex-col gap-5 p-0">
                        <li v-for="(m, mi) in course.modules" :key="m.uuid" class="rounded-card border border-line bg-white">
                            <div class="flex flex-wrap items-end gap-2 border-b border-line bg-lavender-50 p-4">
                                <label class="flex min-w-0 flex-[1_1_260px] flex-col gap-1">
                                    <span class="text-[13px] font-semibold text-ink-soft">{{ t('courseEditor.moduleTitle') }} {{ mi + 1 }}</span>
                                    <input :value="m.title" :disabled="!editable" maxlength="160" class="h-11 rounded-[10px] border-[1.5px] border-line-strong bg-white px-3 font-semibold text-plum focus:border-plum focus:outline-none" @change="renameModule(m.uuid, $event)" />
                                </label>
                                <template v-if="editable">
                                    <button type="button" :class="smallBtn" :disabled="mi === 0 || !!busy" @click="moveModule(mi, -1)">{{ t('courseEditor.moveUp') }}</button>
                                    <button type="button" :class="smallBtn" :disabled="mi === course.modules.length - 1 || !!busy" @click="moveModule(mi, 1)">{{ t('courseEditor.moveDown') }}</button>
                                    <button type="button" class="h-9 rounded-full px-3 text-sm text-kalyna hover:bg-kalyna/5" :disabled="!!busy" @click="deleteModule(m.uuid, m.title)">{{ t('courseEditor.delete') }}</button>
                                </template>
                            </div>

                            <ol class="m-0 list-none p-0">
                                <li v-for="(l, lessonIdx) in m.lessons" :key="l.uuid" class="border-b border-line p-4 last:border-b-0">
                                    <div class="flex flex-wrap items-end gap-2">
                                        <label class="flex min-w-0 flex-[1_1_260px] flex-col gap-1">
                                            <span class="text-[13px] text-ink-muted">{{ t('courseEditor.lessonTitle') }} {{ mi + 1 }}.{{ lessonIdx + 1 }}</span>
                                            <input :value="l.title" :disabled="!editable" maxlength="160" class="h-11 rounded-[10px] border-[1.5px] border-line-strong px-3 focus:border-plum focus:outline-none" @change="updateLesson(l, { title: ($event.target as HTMLInputElement).value.trim() })" />
                                        </label>
                                        <template v-if="editable">
                                            <button type="button" :class="smallBtn" :disabled="lessonIdx === 0 || !!busy" @click="moveLesson(m.uuid, m.lessons, lessonIdx, -1)">{{ t('courseEditor.moveUp') }}</button>
                                            <button type="button" :class="smallBtn" :disabled="lessonIdx === m.lessons.length - 1 || !!busy" @click="moveLesson(m.uuid, m.lessons, lessonIdx, 1)">{{ t('courseEditor.moveDown') }}</button>
                                            <button type="button" class="h-9 rounded-full px-3 text-sm text-kalyna hover:bg-kalyna/5" :disabled="!!busy" @click="deleteLesson(l)">{{ t('courseEditor.delete') }}</button>
                                        </template>
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-5 text-[15px]">
                                        <label class="flex cursor-pointer items-center gap-2">
                                            <input type="checkbox" :checked="l.is_required" :disabled="!editable || !!busy" class="h-5 w-5 accent-plum" @change="updateLesson(l, { is_required: !l.is_required })" />
                                            {{ t('courseEditor.required') }}
                                        </label>
                                        <label class="flex cursor-pointer items-center gap-2">
                                            <input type="checkbox" :checked="l.is_preview" :disabled="!editable || !!busy" class="h-5 w-5 accent-plum" @change="updateLesson(l, { is_preview: !l.is_preview })" />
                                            {{ t('courseEditor.preview') }}
                                        </label>
                                    </div>

                                    <!-- Materials -->
                                    <div class="mt-3">
                                        <p v-if="!l.materials.length" class="m-0 text-sm text-notice-ink">{{ t('courseEditor.noMaterials') }}</p>
                                        <ul v-else class="m-0 flex list-none flex-col gap-1.5 p-0" :aria-label="t('courseEditor.materials')">
                                            <li v-for="mat in l.materials" :key="mat.uuid" class="flex items-center gap-3 rounded-[10px] bg-lavender-50 px-3 py-2 text-[15px]">
                                                <span class="shrink-0 rounded-md bg-white px-2 py-0.5 text-[12px] font-semibold text-plum">{{ t(`courseEditor.types.${mat.type}`) }}</span>
                                                <span class="min-w-0 flex-1 truncate text-ink">
                                                    <template v-if="mat.title"><span class="font-medium">{{ mat.title }}</span> · </template>{{ materialSummary(mat) }}
                                                </span>
                                                <button v-if="editable" type="button" class="h-8 shrink-0 rounded-full px-2 text-sm text-kalyna hover:bg-kalyna/5" :disabled="!!busy" @click="deleteMaterial(mat.uuid)">
                                                    {{ t('courseEditor.delete') }}
                                                </button>
                                            </li>
                                        </ul>

                                        <form v-if="draft.lesson === l.uuid" :id="`mat-${l.uuid}`" class="mt-3 flex flex-col gap-4 rounded-card border border-line p-4" @submit.prevent="addMaterial" @keydown.esc="draft.lesson = null">
                                            <fieldset class="m-0 border-0 p-0">
                                                <legend class="mb-2 text-[15px] font-semibold text-ink">{{ t('courseEditor.type') }}</legend>
                                                <div class="flex flex-wrap gap-2">
                                                    <label v-for="type in MATERIAL_TYPES" :key="type" class="flex cursor-pointer items-center gap-2 rounded-full border px-3.5 py-2 text-[15px]" :class="draft.type === type ? 'border-plum bg-lavender-50 text-plum' : 'border-line-strong'">
                                                        <input v-model="draft.type" type="radio" :value="type" class="accent-plum" />
                                                        {{ t(`courseEditor.types.${type}`) }}
                                                    </label>
                                                </div>
                                            </fieldset>
                                            <BaseInput v-model="draft.title" :label="t('courseEditor.materialTitle')" :maxlength="160" />
                                            <BaseTextarea v-if="draft.type === 'text'" v-model="draft.content" :label="t('courseEditor.text')" :rows="8" :maxlength="50000" required />
                                            <BaseInput v-else-if="draft.type === 'video'" v-model="draft.url" type="url" :label="t('courseEditor.videoUrl')" :hint="t('courseEditor.videoHint')" :placeholder="'https:/' + '/youtu.be/…'" required />
                                            <BaseInput v-else-if="draft.type === 'link'" v-model="draft.url" type="url" :label="t('courseEditor.linkUrl')" :placeholder="'https:/' + '/'" required />
                                            <div v-else-if="draft.type === 'file'" class="flex flex-col gap-1">
                                                <label :for="`file-${l.uuid}`" class="text-[15px] font-semibold text-ink">{{ t('courseEditor.file') }}</label>
                                                <input :id="`file-${l.uuid}`" type="file" class="text-[15px]" @change="draft.file = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                                                <p class="m-0 text-[13px] text-ink-muted">{{ t('courseEditor.fileHint') }}</p>
                                            </div>
                                            <p v-if="draftError" role="alert" class="m-0 text-sm text-kalyna">{{ draftError }}</p>
                                            <div class="flex flex-wrap gap-2">
                                                <BaseButton type="submit" :loading="busy === `mat-${l.uuid}`">
                                                    {{ uploadProgress !== null && busy === `mat-${l.uuid}` ? t('editor.uploading', { n: uploadProgress }) : t('courseEditor.add') }}
                                                </BaseButton>
                                                <BaseButton variant="ghost" @click="draft.lesson = null">{{ t('courseEditor.cancel') }}</BaseButton>
                                            </div>
                                        </form>
                                        <button v-else-if="editable" type="button" class="mt-2 h-9 rounded-full px-3 text-sm font-semibold text-plum hover:bg-lavender-50" @click="openMaterial(l.uuid)">
                                            + {{ t('courseEditor.addMaterial') }}
                                        </button>
                                    </div>
                                </li>
                            </ol>

                            <form v-if="editable" class="flex flex-wrap items-end gap-2 border-t border-line p-4" @submit.prevent="addLesson(m.uuid)">
                                <label class="flex min-w-0 flex-[1_1_260px] flex-col gap-1">
                                    <span class="text-[13px] text-ink-muted">{{ t('courseEditor.newLesson') }}</span>
                                    <input v-model="newLesson[m.uuid]" maxlength="160" class="h-11 rounded-[10px] border-[1.5px] border-line-strong px-3 focus:border-plum focus:outline-none" />
                                </label>
                                <BaseButton type="submit" variant="secondary" :loading="busy === `add-${m.uuid}`">{{ t('courseEditor.addLesson') }}</BaseButton>
                            </form>
                        </li>
                    </ol>

                    <form v-if="editable" class="flex flex-wrap items-end gap-3" @submit.prevent="addModule">
                        <div class="min-w-0 flex-[1_1_300px]">
                            <BaseInput v-model="newModule" :label="t('courseEditor.newModule')" :maxlength="160" />
                        </div>
                        <BaseButton type="submit" :loading="busy === 'module'">{{ t('courseEditor.addModule') }}</BaseButton>
                    </form>
                    <p v-if="formError && busy === null && !dirty" class="m-0 text-sm text-kalyna">{{ formError }}</p>
                </section>
            </template>
        </template>
    </div>
</template>
