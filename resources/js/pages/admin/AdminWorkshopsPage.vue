<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseTextarea from '@/components/ui/BaseTextarea.vue';
import ProductCover from '@/components/products/ProductCover.vue';
import { workshopsApi } from '@/api/workshops';
import { toApiError } from '@/api/http';
import type { WorkshopEditor, WorkshopStatus } from '@/types/workshop';
import { formatPrice } from '@/utils/format';
import { WORKSHOP_STATUS_LABELS, sessionLabel } from '@/utils/workshop';

const TABS: WorkshopStatus[] = ['pending_review', 'published', 'rejected', 'archived', 'cancelled'];
const tab = ref<WorkshopStatus>('pending_review');
const { t } = useI18n();
const items = ref<WorkshopEditor[]>([]);
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
        items.value = (await workshopsApi.review(tab.value)).data;
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

function drop(w: WorkshopEditor, message: string) {
    items.value = items.value.filter((x) => x.uuid !== w.uuid);
    flash.value = message;
}

async function approve(w: WorkshopEditor) {
    busy.value = w.uuid;
    try {
        await workshopsApi.approve(w.uuid);
        drop(w, t('review.published', { name: w.name }));
    } catch (e) {
        flash.value = toApiError(e).message;
    } finally {
        busy.value = null;
    }
}

async function openNote(w: WorkshopEditor, action: 'reject' | 'archive') {
    noteFor.value = { uuid: w.uuid, action };
    note.value = '';
    noteError.value = '';
    await nextTick();
    document.getElementById(`wnote-${w.uuid}`)?.querySelector('textarea')?.focus();
}

async function confirmNote(w: WorkshopEditor) {
    if (!noteFor.value) return;
    if (note.value.trim().length < 5) {
        noteError.value = t('review.productNoteTooShort');
        return;
    }
    busy.value = w.uuid;
    try {
        const action = noteFor.value.action;
        await (action === 'reject' ? workshopsApi.reject(w.uuid, note.value.trim()) : workshopsApi.archiveAsAdmin(w.uuid, note.value.trim()));
        noteFor.value = null;
        drop(w, t(action === 'reject' ? 'review.returned' : 'review.archived', { name: w.name }));
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
        <h1 class="font-flair m-0 text-[40px] leading-tight font-bold tracking-[-0.015em] text-plum">{{ t('adminWorkshops.title') }}</h1>

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
                {{ WORKSHOP_STATUS_LABELS[tabKey] }}
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
            {{ tab === 'pending_review' ? t('adminWorkshops.emptyQueue') : t('adminWorkshops.empty') }}
        </p>

        <ul v-else class="mt-8 mb-0 flex list-none flex-col gap-4 p-0">
            <li v-for="w in items" :key="w.uuid" class="rounded-card border border-line p-5">
                <div class="flex flex-wrap gap-5">
                    <ProductCover :uuid="w.uuid" :name="w.name" :src="w.cover_url" ratio="wide" class="!w-44 shrink-0" />
                    <div class="min-w-0 flex-[1_1_320px]">
                        <RouterLink :to="{ name: 'workshop', params: { slug: w.slug } }" class="text-xl font-semibold text-plum">{{ w.name }}</RouterLink>
                        <div class="mt-1 text-[15px] text-ink-muted">
                            {{ w.maker?.display_name }} · {{ w.format === 'online' ? t('workshop.online') : [w.city, w.location_address].filter(Boolean).join(', ') }} ·
                            {{ t('adminWorkshops.seats', { n: w.capacity }) }} ·
                            <span class="font-semibold text-plum">{{ formatPrice(w.price.amount_minor, w.price.currency) }}</span>
                        </div>
                        <ul class="mt-2 mb-0 list-none p-0 text-[15px] text-ink">
                            <li v-for="s in w.sessions" :key="s.uuid">{{ sessionLabel(s, w.timezone) }} <span class="text-ink-muted">({{ w.timezone }})</span></li>
                        </ul>
                        <p class="mt-3 mb-0 text-ink" :class="expanded === w.uuid ? '' : 'line-clamp-2'">{{ w.description }}</p>
                        <button v-if="(w.description?.length ?? 0) > 160" type="button" class="mt-1 text-sm font-semibold text-plum" @click="expanded = expanded === w.uuid ? null : w.uuid">
                            {{ expanded === w.uuid ? t('review.collapse') : t('review.readMore') }}
                        </button>
                        <div v-if="!w.translations?.[w.content_locale === 'en' ? 'uk' : 'en']?.name" class="mt-2 text-sm text-ink-muted">
                            {{ w.content_locale === 'en' ? t('review.noUkrainian') : t('review.noEnglish') }}
                        </div>
                        <p v-if="w.review_note" class="mt-2 mb-0 text-sm text-ink-muted"><span class="font-semibold">{{ t('review.previousNote') }}</span> {{ w.review_note }}</p>
                    </div>
                    <div class="flex flex-wrap items-start gap-2">
                        <template v-if="tab === 'pending_review'">
                            <BaseButton :loading="busy === w.uuid" @click="approve(w)">{{ t('review.publish') }}</BaseButton>
                            <BaseButton variant="secondary" @click="openNote(w, 'reject')">{{ t('review.returnForChanges') }}</BaseButton>
                        </template>
                        <BaseButton v-else-if="tab === 'published'" variant="secondary" @click="openNote(w, 'archive')">{{ t('review.takeOffSale') }}</BaseButton>
                    </div>
                </div>

                <form v-if="noteFor?.uuid === w.uuid" :id="`wnote-${w.uuid}`" class="mt-5 flex flex-col gap-3 border-t border-line pt-5" @submit.prevent="confirmNote(w)" @keydown.esc="noteFor = null">
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
                        <BaseButton type="submit" :loading="busy === w.uuid">{{ noteFor.action === 'reject' ? t('review.returnForChanges') : t('review.takeOffSale') }}</BaseButton>
                        <BaseButton variant="ghost" @click="noteFor = null">{{ t('review.cancel') }}</BaseButton>
                    </div>
                </form>
            </li>
        </ul>
    </div>
</template>
