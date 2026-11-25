<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { INTL_LOCALE, currentLocale } from '@/i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseTextarea from '@/components/ui/BaseTextarea.vue';
import MakerAvatar from '@/components/makers/MakerAvatar.vue';
import { makersApi } from '@/api/makers';
import { toApiError } from '@/api/http';
import type { MakerProfile, MakerStatus } from '@/types/maker';
import { MAKER_STATUS_LABELS } from '@/utils/makerStatus';

const TABS: MakerStatus[] = ['pending_review', 'approved', 'rejected', 'suspended'];

const tab = ref<MakerStatus>('pending_review');
const { t } = useI18n();
const items = ref<MakerProfile[]>([]);
const total = ref(0);
const loading = ref(true);
const error = ref('');

/** Profile with the note form open (reject or suspend) */
const noteFor = ref<{ uuid: string; action: 'reject' | 'suspend' } | null>(null);
const note = ref('');
const noteError = ref('');
const busy = ref<string | null>(null);
const flash = ref('');

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await makersApi.reviewQueue(tab.value);
        items.value = res.data;
        total.value = res.meta.total;
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

function removeFromList(uuid: string, message: string) {
    items.value = items.value.filter((m) => m.uuid !== uuid);
    total.value -= 1;
    flash.value = message;
}

async function act(maker: MakerProfile, action: 'approve' | 'reinstate') {
    busy.value = maker.uuid;
    try {
        await makersApi.review(maker.uuid, action);
        removeFromList(maker.uuid, action === 'approve' ? t('review.published', { name: maker.display_name }) : t('review.reinstated', { name: maker.display_name }));
    } catch (e) {
        flash.value = toApiError(e).message;
    } finally {
        busy.value = null;
    }
}

async function openNote(maker: MakerProfile, action: 'reject' | 'suspend') {
    noteFor.value = { uuid: maker.uuid, action };
    note.value = '';
    noteError.value = '';
    await nextTick();
    document.getElementById(`note-${maker.uuid}`)?.querySelector('textarea')?.focus();
}

async function confirmNote(maker: MakerProfile) {
    if (!noteFor.value) return;
    if (note.value.trim().length < 5) {
        noteError.value = t('review.makerNoteTooShort');
        return;
    }
    busy.value = maker.uuid;
    try {
        await makersApi.review(maker.uuid, noteFor.value.action, note.value.trim());
        const key = noteFor.value.action === 'reject' ? 'review.returned' : 'review.suspended';
        noteFor.value = null;
        removeFromList(maker.uuid, t(key, { name: maker.display_name }));
    } catch (e) {
        const err = toApiError(e);
        noteError.value = err.fields.note ?? err.message;
    } finally {
        busy.value = null;
    }
}

const dateFmt = computed(() => new Intl.DateTimeFormat(INTL_LOCALE[currentLocale()], { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }));
</script>

<template>
    <div class="max-w-[1080px]">
        <h1 class="font-flair m-0 text-[40px] leading-tight font-bold tracking-[-0.015em] text-plum">{{ t('review.makersTitle') }}</h1>

        <div role="tablist" :aria-label="t('review.makerStatus')" class="mt-8 flex flex-wrap gap-2">
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
                {{ MAKER_STATUS_LABELS[tabKey] }}
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
            {{ tab === 'pending_review' ? t('review.makersEmptyQueue') : t('review.makersEmpty') }}
        </p>

        <ul v-else class="mt-8 mb-0 flex list-none flex-col gap-4 p-0">
            <li v-for="maker in items" :key="maker.uuid" class="rounded-card border border-line p-6">
                <div class="flex flex-wrap gap-6">
                    <MakerAvatar :uuid="maker.uuid" :name="maker.display_name" :src="maker.avatar_url" shape="circle" class="w-20 shrink-0" />
                    <div class="min-w-0 flex-[1_1_320px]">
                        <RouterLink :to="{ name: 'maker', params: { slug: maker.slug } }" class="text-xl font-semibold text-plum">
                            {{ maker.display_name }}
                        </RouterLink>
                        <div class="mt-1 text-[15px] text-ink-muted">
                            {{ maker.owner?.email }}<template v-if="maker.city"> · {{ maker.city }}</template>
                            <template v-if="maker.updated_at"> · {{ t('review.updated', { date: dateFmt.format(new Date(maker.updated_at)) }) }}</template>
                        </div>
                        <p v-if="maker.bio" class="mt-3 mb-0 text-ink">{{ maker.bio }}</p>
                        <p v-else class="mt-3 mb-0 text-ink-muted">{{ t('review.noDescription') }}</p>
                        <p v-if="maker.review_note" class="mt-3 mb-0 text-sm text-ink-muted">
                            <span class="font-semibold">{{ t('review.previousNote') }}</span> {{ maker.review_note }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-start gap-2">
                        <template v-if="tab === 'pending_review'">
                            <BaseButton :loading="busy === maker.uuid" @click="act(maker, 'approve')">{{ t('review.publish') }}</BaseButton>
                            <BaseButton variant="secondary" @click="openNote(maker, 'reject')">{{ t('review.returnForChanges') }}</BaseButton>
                        </template>
                        <BaseButton v-else-if="tab === 'approved'" variant="secondary" @click="openNote(maker, 'suspend')">{{ t('review.suspend') }}</BaseButton>
                        <BaseButton v-else-if="tab === 'suspended'" :loading="busy === maker.uuid" @click="act(maker, 'reinstate')">{{ t('review.reinstate') }}</BaseButton>
                    </div>
                </div>

                <form
                    v-if="noteFor?.uuid === maker.uuid"
                    :id="`note-${maker.uuid}`"
                    class="mt-6 flex flex-col gap-3 border-t border-line pt-6"
                    @submit.prevent="confirmNote(maker)"
                    @keydown.esc="noteFor = null"
                >
                    <BaseTextarea
                        v-model="note"
                        :label="noteFor.action === 'reject' ? t('review.whatToFix') : t('review.suspendReason')"
                        :rows="3"
                        :maxlength="2000"
                        :hint="t('review.makerSees')"
                        :error="noteError"
                        required
                    />
                    <div class="flex flex-wrap gap-2">
                        <BaseButton type="submit" :loading="busy === maker.uuid">
                            {{ noteFor.action === 'reject' ? t('review.returnForChanges') : t('review.suspend') }}
                        </BaseButton>
                        <BaseButton variant="ghost" @click="noteFor = null">{{ t('review.cancel') }}</BaseButton>
                    </div>
                </form>
            </li>
        </ul>
    </div>
</template>
