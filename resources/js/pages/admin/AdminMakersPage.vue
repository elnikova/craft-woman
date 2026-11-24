<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseTextarea from '@/components/ui/BaseTextarea.vue';
import MakerAvatar from '@/components/makers/MakerAvatar.vue';
import { makersApi } from '@/api/makers';
import { toApiError } from '@/api/http';
import type { MakerProfile, MakerStatus } from '@/types/maker';
import { MAKER_STATUS_LABELS } from '@/utils/makerStatus';

const TABS: MakerStatus[] = ['pending_review', 'approved', 'rejected', 'suspended'];

const tab = ref<MakerStatus>('pending_review');
const items = ref<MakerProfile[]>([]);
const total = ref(0);
const loading = ref(true);
const error = ref('');

/** Профіль, для якого відкрито форму з коментарем (відхилення чи призупинення) */
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
        removeFromList(maker.uuid, action === 'approve' ? `«${maker.display_name}» опубліковано` : `«${maker.display_name}» відновлено`);
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
        noteError.value = 'Напишіть, що саме потрібно змінити (щонайменше 5 символів). Майстер побачить цей коментар.';
        return;
    }
    busy.value = maker.uuid;
    try {
        await makersApi.review(maker.uuid, noteFor.value.action, note.value.trim());
        const verb = noteFor.value.action === 'reject' ? 'повернуто на доопрацювання' : 'призупинено';
        noteFor.value = null;
        removeFromList(maker.uuid, `«${maker.display_name}» ${verb}`);
    } catch (e) {
        const err = toApiError(e);
        noteError.value = err.fields.note ?? err.message;
    } finally {
        busy.value = null;
    }
}

const dateFmt = new Intl.DateTimeFormat('uk-UA', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <div class="max-w-[1080px]">
        <h1 class="font-flair m-0 text-[40px] leading-tight font-bold tracking-[-0.015em] text-plum">Перевірка майстрів</h1>

        <div role="tablist" aria-label="Статус профілю" class="mt-8 flex flex-wrap gap-2">
            <button
                v-for="t in TABS"
                :key="t"
                type="button"
                role="tab"
                :aria-selected="tab === t"
                class="h-11 rounded-full border px-[18px] text-[15px]"
                :class="tab === t ? 'border-plum bg-plum text-white' : 'border-line-strong bg-white text-ink hover:border-plum'"
                @click="tab = t"
            >
                {{ MAKER_STATUS_LABELS[t] }}
            </button>
        </div>

        <p v-if="flash" role="status" class="mt-6 mb-0 rounded-[10px] bg-lavender-50 px-4 py-3 text-[15px] text-plum">{{ flash }}</p>

        <div v-if="loading" class="mt-8 space-y-4" aria-busy="true" aria-label="Завантаження">
            <div v-for="i in 3" :key="i" class="h-32 animate-pulse rounded-card bg-lavender-50" />
        </div>

        <div v-else-if="error" role="alert" class="mt-8 flex flex-col items-start gap-4 rounded-card border border-kalyna/30 bg-kalyna/5 p-6">
            <p class="m-0 text-kalyna">{{ error }}</p>
            <BaseButton variant="secondary" @click="load">Спробувати ще раз</BaseButton>
        </div>

        <p v-else-if="items.length === 0" class="mt-10 text-lg text-ink-muted">
            {{ tab === 'pending_review' ? 'Черга порожня — усі профілі перевірено.' : 'Тут поки нікого.' }}
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
                            <template v-if="maker.updated_at"> · оновлено {{ dateFmt.format(new Date(maker.updated_at)) }}</template>
                        </div>
                        <p v-if="maker.bio" class="mt-3 mb-0 text-ink">{{ maker.bio }}</p>
                        <p v-else class="mt-3 mb-0 text-ink-muted">Без опису.</p>
                        <p v-if="maker.review_note" class="mt-3 mb-0 text-sm text-ink-muted">
                            <span class="font-semibold">Попередній коментар:</span> {{ maker.review_note }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-start gap-2">
                        <template v-if="tab === 'pending_review'">
                            <BaseButton :loading="busy === maker.uuid" @click="act(maker, 'approve')">Опублікувати</BaseButton>
                            <BaseButton variant="secondary" @click="openNote(maker, 'reject')">Повернути на правки</BaseButton>
                        </template>
                        <BaseButton v-else-if="tab === 'approved'" variant="secondary" @click="openNote(maker, 'suspend')">Призупинити</BaseButton>
                        <BaseButton v-else-if="tab === 'suspended'" :loading="busy === maker.uuid" @click="act(maker, 'reinstate')">Відновити</BaseButton>
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
                        :label="noteFor.action === 'reject' ? 'Що потрібно виправити' : 'Причина призупинення'"
                        :rows="3"
                        :maxlength="2000"
                        hint="Майстер побачить цей коментар у своїй майстерні."
                        :error="noteError"
                        required
                    />
                    <div class="flex flex-wrap gap-2">
                        <BaseButton type="submit" :loading="busy === maker.uuid">
                            {{ noteFor.action === 'reject' ? 'Повернути на правки' : 'Призупинити' }}
                        </BaseButton>
                        <BaseButton variant="ghost" @click="noteFor = null">Скасувати</BaseButton>
                    </div>
                </form>
            </li>
        </ul>
    </div>
</template>
