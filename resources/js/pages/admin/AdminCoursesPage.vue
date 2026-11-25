<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseTextarea from '@/components/ui/BaseTextarea.vue';
import ProductCover from '@/components/products/ProductCover.vue';
import { coursesApi } from '@/api/courses';
import { toApiError } from '@/api/http';
import type { CourseEditor, CourseStatus } from '@/types/course';
import { accessLabel } from '@/utils/course';
import { formatPrice } from '@/utils/format';
import { PRODUCT_STATUS_LABELS } from '@/utils/productStatus';

const TABS: CourseStatus[] = ['pending_review', 'published', 'rejected', 'archived'];
const tab = ref<CourseStatus>('pending_review');
const { t } = useI18n();
const items = ref<CourseEditor[]>([]);
const loading = ref(true);
const error = ref('');
const flash = ref('');
const busy = ref<string | null>(null);
const noteFor = ref<{ uuid: string; action: 'reject' | 'archive' } | null>(null);
const note = ref('');
const noteError = ref('');
const expanded = ref<string | null>(null);

async function load() {
    loading.value = true;
    error.value = '';
    try {
        items.value = (await coursesApi.review(tab.value)).data;
    } catch (e) {
        error.value = toApiError(e).message;
    } finally {
        loading.value = false;
    }
}

watch(tab, () => {
    noteFor.value = null;
    flash.value = '';
    void load();
}, { immediate: true });

function drop(c: CourseEditor, message: string) {
    items.value = items.value.filter((x) => x.uuid !== c.uuid);
    flash.value = message;
}

async function approve(c: CourseEditor) {
    busy.value = c.uuid;
    try {
        await coursesApi.approve(c.uuid);
        drop(c, t('review.published', { name: c.name }));
    } catch (e) {
        flash.value = toApiError(e).message;
    } finally {
        busy.value = null;
    }
}

async function openNote(c: CourseEditor, action: 'reject' | 'archive') {
    noteFor.value = { uuid: c.uuid, action };
    note.value = '';
    noteError.value = '';
    await nextTick();
    document.getElementById(`cnote-${c.uuid}`)?.querySelector('textarea')?.focus();
}

async function confirmNote(c: CourseEditor) {
    if (!noteFor.value) return;
    if (note.value.trim().length < 5) {
        noteError.value = t('review.productNoteTooShort');
        return;
    }
    busy.value = c.uuid;
    try {
        const action = noteFor.value.action;
        await (action === 'reject' ? coursesApi.reject(c.uuid, note.value.trim()) : coursesApi.archiveAsAdmin(c.uuid, note.value.trim()));
        noteFor.value = null;
        drop(c, t(action === 'reject' ? 'review.returned' : 'review.archived', { name: c.name }));
    } catch (e) {
        const err = toApiError(e);
        noteError.value = err.fields.note ?? err.message;
    } finally {
        busy.value = null;
    }
}
</script>

<template>
    <div class="max-w-[1080px]">
        <h1 class="font-flair m-0 text-[40px] leading-tight font-bold tracking-[-0.015em] text-plum">{{ t('adminCourses.title') }}</h1>

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
                {{ PRODUCT_STATUS_LABELS[tabKey] }}
            </button>
        </div>

        <p v-if="flash" role="status" class="mt-6 mb-0 rounded-[10px] bg-lavender-50 px-4 py-3 text-[15px] text-plum">{{ flash }}</p>

        <div v-if="loading" class="mt-8 space-y-4" aria-busy="true" :aria-label="t('common.loading')">
            <div v-for="i in 3" :key="i" class="h-32 animate-pulse rounded-card bg-lavender-50" />
        </div>

        <div v-else-if="error" role="alert" class="mt-8 flex flex-col items-start gap-4 rounded-card border border-kalyna/30 bg-kalyna/5 p-6">
            <p class="m-0 text-kalyna">{{ error }}</p>
            <BaseButton variant="secondary" @click="load">{{ t('common.retry') }}</BaseButton>
        </div>

        <p v-else-if="items.length === 0" class="mt-10 text-lg text-ink-muted">
            {{ tab === 'pending_review' ? t('adminCourses.emptyQueue') : t('adminCourses.empty') }}
        </p>

        <ul v-else class="mt-8 mb-0 flex list-none flex-col gap-4 p-0">
            <li v-for="c in items" :key="c.uuid" class="rounded-card border border-line p-5">
                <div class="flex flex-wrap gap-5">
                    <ProductCover :uuid="c.uuid" :name="c.name" :src="c.cover_url" ratio="wide" class="!w-44 shrink-0" />
                    <div class="min-w-0 flex-[1_1_320px]">
                        <RouterLink :to="{ name: 'course', params: { slug: c.slug } }" class="text-xl font-semibold text-plum">{{ c.name }}</RouterLink>
                        <div class="mt-1 text-[15px] text-ink-muted">
                            {{ c.maker?.display_name }} · {{ c.category?.name ?? t('review.noCategory') }} · {{ accessLabel(c) }} ·
                            <span class="font-semibold text-plum">{{ formatPrice(c.price.amount_minor, c.price.currency) }}</span>
                        </div>
                        <p class="mt-3 mb-0 text-ink" :class="expanded === c.uuid ? '' : 'line-clamp-2'">{{ c.description }}</p>
                        <button v-if="(c.description?.length ?? 0) > 160" type="button" class="mt-1 text-sm font-semibold text-plum" @click="expanded = expanded === c.uuid ? null : c.uuid">
                            {{ expanded === c.uuid ? t('review.collapse') : t('review.readMore') }}
                        </button>
                        <div class="mt-2 text-sm text-ink-muted">
                            {{ t('adminCourses.outline', { modules: t('coursePage.modules', { n: c.modules_count }), lessons: t('coursePage.lessons', { n: c.lessons_count }) }) }}
                            <template v-if="!c.translations?.[c.content_locale === 'en' ? 'uk' : 'en']?.name">
                                · {{ c.content_locale === 'en' ? t('review.noUkrainian') : t('review.noEnglish') }}
                            </template>
                        </div>
                        <details class="mt-2 text-sm">
                            <summary class="cursor-pointer font-semibold text-plum">{{ t('coursePage.program') }}</summary>
                            <ol class="mt-2 mb-0 pl-5">
                                <li v-for="m in c.modules" :key="m.uuid" class="mt-1">
                                    <span class="font-semibold">{{ m.title }}</span>
                                    <ul class="mb-0 pl-4">
                                        <li v-for="l in m.lessons" :key="l.uuid">
                                            {{ l.title }}
                                            <span class="text-ink-muted">({{ l.materials.map((x) => t(`coursePage.materials.${x.type}`)).join(', ') || '—' }})</span>
                                        </li>
                                    </ul>
                                </li>
                            </ol>
                        </details>
                        <p v-if="c.review_note" class="mt-2 mb-0 text-sm text-ink-muted"><span class="font-semibold">{{ t('review.previousNote') }}</span> {{ c.review_note }}</p>
                    </div>
                    <div class="flex flex-wrap items-start gap-2">
                        <template v-if="tab === 'pending_review'">
                            <BaseButton :loading="busy === c.uuid" @click="approve(c)">{{ t('review.publish') }}</BaseButton>
                            <BaseButton variant="secondary" @click="openNote(c, 'reject')">{{ t('review.returnForChanges') }}</BaseButton>
                        </template>
                        <BaseButton v-else-if="tab === 'published'" variant="secondary" @click="openNote(c, 'archive')">{{ t('review.takeOffSale') }}</BaseButton>
                    </div>
                </div>

                <form v-if="noteFor?.uuid === c.uuid" :id="`cnote-${c.uuid}`" class="mt-5 flex flex-col gap-3 border-t border-line pt-5" @submit.prevent="confirmNote(c)" @keydown.esc="noteFor = null">
                    <BaseTextarea
                        v-model="note"
                        :label="noteFor.action === 'reject' ? t('review.whatToFix') : t('review.archiveReason')"
                        :rows="3"
                        :maxlength="2000"
                        :hint="t('review.makerSeesAccount')"
                        :error="noteError"
                        required
                    />
                    <div class="flex flex-wrap gap-2">
                        <BaseButton type="submit" :loading="busy === c.uuid">{{ noteFor.action === 'reject' ? t('review.returnForChanges') : t('review.takeOffSale') }}</BaseButton>
                        <BaseButton variant="ghost" @click="noteFor = null">{{ t('review.cancel') }}</BaseButton>
                    </div>
                </form>
            </li>
        </ul>
    </div>
</template>
