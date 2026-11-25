<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { onBeforeRouteLeave, useRouter } from 'vue-router';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseInput from '@/components/ui/BaseInput.vue';
import BaseTextarea from '@/components/ui/BaseTextarea.vue';
import ContentLanguage from '@/components/ui/ContentLanguage.vue';
import ProductCover from '@/components/products/ProductCover.vue';
import WorkshopStatusBadge from '@/components/workshops/WorkshopStatusBadge.vue';
import { workshopsApi } from '@/api/workshops';
import { taxonomyApi } from '@/api/taxonomy';
import { toApiError, type FieldErrors } from '@/api/http';
import type { Locale } from '@/i18n';
import type { Currency } from '@/types/product';
import type { Category } from '@/types/taxonomy';
import type { Participant, WorkshopEditor, WorkshopFormat, WorkshopPayload } from '@/types/workshop';
import { countries, formatDate } from '@/utils/orderStatus';
import { inputToMinor, minorToInput } from '@/utils/format';
import { TIMEZONES, browserTimezone } from '@/utils/workshop';

const props = defineProps<{ uuid?: string }>();
const router = useRouter();
const { t } = useI18n();

const workshop = ref<WorkshopEditor | null>(null);
const categories = ref<Category[]>([]);
const loading = ref(true);
const loadError = ref('');

const form = reactive({
    name: '',
    description: '',
    price: '',
    currency: 'EUR' as Currency,
    capacity: '8',
    format: 'offline' as WorkshopFormat,
    timezone: 'Europe/Kyiv',
    country: 'UA',
    city: '',
    address: '',
    meeting_url: '',
    note: '',
    category_uuid: '',
    content_locale: 'uk' as Locale,
    tr_name: '',
    tr_description: '',
    tr_note: '',
});
const otherLocale = computed<Locale>(() => (form.content_locale === 'uk' ? 'en' : 'uk'));
const saved = ref('');
const dirty = computed(() => JSON.stringify(form) !== saved.value);
const zones = computed(() => [...new Set([...TIMEZONES, browserTimezone(), form.timezone])]);

const errors = ref<FieldErrors>({});
const formError = ref('');
const notice = ref('');
const busy = ref<string | null>(null);
const confirmDelete = ref(false);

const isNew = computed(() => !workshop.value);
const status = computed(() => workshop.value?.status ?? 'draft');
const editable = computed(() => isNew.value || workshop.value?.is_editable === true);
const hasBookings = computed(() => (workshop.value?.seats_booked ?? 0) > 0);

function fill(w: WorkshopEditor, resetForm = true) {
    workshop.value = w;
    if (!resetForm) return;
    const other = w.content_locale === 'en' ? 'uk' : 'en';
    Object.assign(form, {
        name: w.name,
        description: w.description ?? '',
        price: minorToInput(w.price.amount_minor || null),
        currency: w.price.currency,
        capacity: String(w.capacity),
        format: w.format,
        timezone: w.timezone,
        country: w.country_code ?? 'UA',
        city: w.city ?? '',
        address: w.location_address ?? '',
        meeting_url: w.external_url ?? '',
        note: w.participant_note ?? '',
        category_uuid: w.category?.uuid ?? '',
        content_locale: w.content_locale ?? 'uk',
        tr_name: w.translations?.[other]?.name ?? '',
        tr_description: w.translations?.[other]?.description ?? '',
        tr_note: w.translations?.[other]?.participant_note ?? '',
    });
    saved.value = JSON.stringify(form);
}

function payload(): WorkshopPayload | null {
    const price = inputToMinor(form.price);
    const local: FieldErrors = {};
    if (Number.isNaN(price)) local.price_amount_minor = t('editor.priceFormat');
    if (!/^\d+$/.test(form.capacity.trim())) local.capacity = t('editor.badStock');
    if (Object.keys(local).length) {
        errors.value = local;
        formError.value = t('editor.checkFields');
        return null;
    }
    const offline = form.format === 'offline';
    return {
        name: form.name.trim(),
        description: form.description.trim() || null,
        price_amount_minor: price ?? 0,
        currency: form.currency,
        capacity: Number(form.capacity),
        format: form.format,
        timezone: form.timezone,
        location_country_code: offline ? form.country : null,
        location_city: offline ? form.city.trim() || null : null,
        location_address: offline ? form.address.trim() || null : null,
        external_url: offline ? null : form.meeting_url.trim() || null,
        participant_note: form.note.trim() || null,
        category_uuid: form.category_uuid || null,
        content_locale: form.content_locale,
        translations: {
            [otherLocale.value]: { name: form.tr_name.trim() || null, description: form.tr_description.trim() || null, participant_note: form.tr_note.trim() || null },
        },
    };
}

async function load() {
    loading.value = true;
    loadError.value = '';
    try {
        const [cats, w] = await Promise.all([taxonomyApi.categories(), props.uuid ? workshopsApi.mineOne(props.uuid) : Promise.resolve(null)]);
        categories.value = cats;
        if (w) {
            fill(w);
            void loadParticipants();
        } else {
            saved.value = JSON.stringify(form);
        }
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
    const w = await run('save', () => (workshop.value ? workshopsApi.update(workshop.value.uuid, body) : workshopsApi.create(body)), t('workshopEditor.saved'));
    if (!w) return false;
    fill(w);
    if (wasNew) {
        notice.value = t('workshopEditor.created');
        await router.replace({ name: 'studio-workshop', params: { uuid: w.uuid } });
    }
    return true;
}

async function submit() {
    if ((dirty.value || isNew.value) && !(await save())) return;
    const w = await run('submit', () => workshopsApi.submit(workshop.value!.uuid), t('workshopEditor.submitted'));
    if (w) fill(w);
}

async function lifecycle(action: 'archive' | 'relist') {
    const w = await run(action, () => workshopsApi[action](workshop.value!.uuid), action === 'archive' ? t('workshopEditor.archivedDone') : t('workshopEditor.relisted'));
    if (w) fill(w);
}

async function destroy() {
    const ok = await run('delete', () => workshopsApi.remove(workshop.value!.uuid));
    if (ok !== null) {
        saved.value = JSON.stringify(form);
        await router.push({ name: 'studio-workshops' });
    }
}

// ---- cancellation ----
const cancelOpen = ref(false);
const cancelReason = ref('');
const cancelError = ref('');
async function openCancel() {
    cancelOpen.value = true;
    cancelReason.value = '';
    cancelError.value = '';
    await nextTick();
    document.getElementById('cancel-form')?.querySelector('textarea')?.focus();
}
async function confirmCancel() {
    if (cancelReason.value.trim().length < 5) {
        cancelError.value = t('review.productNoteTooShort');
        return;
    }
    const w = await run('cancel', () => workshopsApi.cancel(workshop.value!.uuid, cancelReason.value.trim()), t('workshopEditor.cancelledDone'));
    if (w) {
        fill(w);
        cancelOpen.value = false;
        void loadParticipants();
    } else {
        cancelError.value = errors.value.reason ?? formError.value;
    }
}

// ---- cover ----
async function onCover(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    (e.target as HTMLInputElement).value = '';
    if (!file) return;
    const w = await run('cover', () => workshopsApi.cover(workshop.value!.uuid, file));
    if (w) fill(w, false);
}

// ---- schedule (saved immediately; doesn't reset unsaved details) ----
const sessionEdits = reactive<Record<string, { start: string; end: string }>>({});
const newSession = reactive({ start: '', end: '' });
const sessionError = ref('');

watch(
    () => workshop.value?.sessions,
    (list) => {
        for (const key of Object.keys(sessionEdits)) delete sessionEdits[key];
        for (const s of list ?? []) sessionEdits[s.uuid] = { start: s.starts_local, end: s.ends_local };
    },
    { immediate: true },
);

/** Picking a start fills the end three hours later on the same day, a common workshop length */
function suggestEnd(target: { start: string; end: string }) {
    if (!target.start || target.end) return;
    const [date, time] = target.start.split('T');
    const [h, m] = (time ?? '00:00').split(':').map(Number);
    target.end = `${date}T${String(Math.min((h ?? 0) + 3, 23)).padStart(2, '0')}:${String(m ?? 0).padStart(2, '0')}`;
}

async function schedule(key: string, fn: () => Promise<WorkshopEditor>) {
    sessionError.value = '';
    const w = await run(key, fn);
    if (w) fill(w, false);
    else sessionError.value = Object.values(errors.value)[0] ?? formError.value;
    formError.value = '';
    return w;
}

async function addSession() {
    if (!newSession.start || !newSession.end) return;
    if (await schedule('session-new', () => workshopsApi.addSession(workshop.value!.uuid, newSession.start, newSession.end))) {
        newSession.start = '';
        newSession.end = '';
    }
}

function changed(uuid: string): boolean {
    const s = workshop.value?.sessions.find((x) => x.uuid === uuid);
    const e = sessionEdits[uuid];
    return !!s && !!e && (s.starts_local !== e.start || s.ends_local !== e.end);
}

// ---- participants ----
const participants = ref<Participant[]>([]);
const seatsMeta = ref<{ seats_confirmed: number; seats_pending: number; capacity: number } | null>(null);
const copied = ref(false);
async function loadParticipants() {
    if (!workshop.value || workshop.value.status === 'draft') return;
    try {
        const res = await workshopsApi.participants(workshop.value.uuid);
        participants.value = res.data;
        seatsMeta.value = res.meta;
    } catch {
        participants.value = [];
    }
}
async function copyEmails() {
    await navigator.clipboard.writeText(participants.value.map((p) => p.email).filter(Boolean).join(', '));
    copied.value = true;
    setTimeout(() => (copied.value = false), 2500);
}

watch(() => props.uuid, (u) => { if (u && u !== workshop.value?.uuid) void load(); });

function beforeUnload(e: BeforeUnloadEvent) {
    if (dirty.value) e.preventDefault();
}
onMounted(() => {
    void load();
    window.addEventListener('beforeunload', beforeUnload);
});
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));
onBeforeRouteLeave((to) => (dirty.value && to.name !== 'studio-workshop' ? window.confirm(t('workshopEditor.leaveConfirm')) : true));

const selectClass = 'h-[52px] rounded-control border-[1.5px] border-line-strong bg-white px-4 text-base text-ink focus:border-plum focus:outline-none disabled:bg-lavender-50';
const dateClass = 'h-11 rounded-[10px] border-[1.5px] border-line-strong bg-white px-3 text-[15px] focus:border-plum focus:outline-none disabled:bg-lavender-50';
</script>

<template>
    <div class="max-w-[920px]">
        <RouterLink :to="{ name: 'studio-workshops' }" class="text-[15px] text-plum">{{ t('workshopEditor.all') }}</RouterLink>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-4">
            <h1 class="font-flair m-0 text-[36px] leading-tight font-bold tracking-[-0.015em] text-plum">{{ isNew ? t('workshopEditor.new') : form.name || t('workshopEditor.new') }}</h1>
            <WorkshopStatusBadge v-if="workshop?.status" :status="workshop.status" />
        </div>

        <div v-if="loading" class="mt-8 space-y-4" aria-busy="true" :aria-label="t('common.loading')">
            <div v-for="i in 4" :key="i" class="h-14 animate-pulse rounded-control bg-lavender-50" />
        </div>

        <div v-else-if="loadError" role="alert" class="mt-8 flex flex-col items-start gap-4 rounded-card border border-kalyna/30 bg-kalyna/5 p-6">
            <p class="m-0 text-kalyna">{{ loadError }}</p>
            <BaseButton variant="secondary" @click="load">{{ t('common.retry') }}</BaseButton>
        </div>

        <template v-else>
            <!-- What is happening with the workshop -->
            <div v-if="workshop" class="mt-6 flex flex-col gap-2 rounded-card p-5" :class="status === 'rejected' || status === 'cancelled' ? 'bg-kalyna/5' : 'bg-lavender-50'">
                <p class="m-0" :class="status === 'rejected' || status === 'cancelled' ? 'font-semibold text-kalyna' : 'text-ink'">{{ t(`workshopEditor.hints.${status}`) }}</p>
                <p v-if="workshop.review_note && ['rejected', 'archived', 'cancelled'].includes(status)" class="m-0 text-ink">
                    <span class="font-semibold">{{ t('workshopEditor.moderatorNote') }}</span> {{ workshop.review_note }}
                </p>
                <div v-if="workshop.missing.length && (status === 'draft' || status === 'rejected')">
                    <p class="m-0 font-semibold text-ink">{{ t('workshopEditor.missingTitle') }}</p>
                    <ul class="mt-1 mb-0 pl-5 text-ink">
                        <li v-for="key in workshop.missing" :key="key">{{ t(`workshopEditor.req.${key}`) }}</li>
                    </ul>
                </div>
                <RouterLink :to="{ name: 'workshop', params: { slug: workshop.slug } }" class="text-[15px] font-semibold text-plum">{{ t('workshopEditor.previewPage') }}</RouterLink>
            </div>

            <form class="mt-8 flex flex-col gap-10" novalidate @submit.prevent="save">
                <fieldset :disabled="!editable" class="m-0 flex flex-col gap-10 border-0 p-0">
                    <section class="flex flex-col gap-6" aria-labelledby="sec-details">
                        <h2 id="sec-details" class="m-0 text-xl font-semibold text-plum">{{ t('workshopEditor.details') }}</h2>
                        <BaseInput v-model="form.name" :label="t('workshopEditor.name')" :maxlength="160" required :error="errors.name" />
                        <BaseTextarea v-model="form.description" :label="t('workshopEditor.description')" :rows="6" :maxlength="10000" :hint="t('workshopEditor.descriptionHint')" :error="errors.description" />
                        <div class="flex flex-col gap-2 md:max-w-[420px]">
                            <label for="category" class="text-[15px] font-semibold text-ink">{{ t('workshopEditor.category') }}</label>
                            <select id="category" v-model="form.category_uuid" :class="selectClass">
                                <option value="">{{ t('workshopEditor.chooseCategory') }}</option>
                                <optgroup v-for="c in categories" :key="c.uuid" :label="c.name">
                                    <option :value="c.uuid">{{ t('editor.general', { name: c.name }) }}</option>
                                    <option v-for="ch in c.children" :key="ch.uuid" :value="ch.uuid">{{ ch.name }}</option>
                                </optgroup>
                            </select>
                        </div>
                    </section>

                    <section class="flex flex-col gap-6" aria-labelledby="sec-where">
                        <h2 id="sec-where" class="m-0 text-xl font-semibold text-plum">{{ t('workshopEditor.where') }}</h2>
                        <fieldset class="m-0 border-0 p-0">
                            <legend class="mb-2 text-[15px] font-semibold text-ink">{{ t('workshopEditor.format') }}</legend>
                            <div class="flex flex-wrap gap-3">
                                <label
                                    v-for="opt in (['offline', 'online'] as const)"
                                    :key="opt"
                                    class="flex cursor-pointer items-center gap-2 rounded-control border-2 px-4 py-3"
                                    :class="[form.format === opt ? 'border-plum bg-lavender-50' : 'border-line', hasBookings ? 'cursor-not-allowed opacity-70' : '']"
                                >
                                    <input v-model="form.format" type="radio" :value="opt" :disabled="hasBookings" class="h-5 w-5 accent-plum" />
                                    {{ t(`workshopEditor.${opt}`) }}
                                </label>
                            </div>
                        </fieldset>
                        <template v-if="form.format === 'offline'">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="flex flex-col gap-2">
                                    <label for="country" class="text-[15px] font-semibold text-ink">{{ t('workshopEditor.country') }}</label>
                                    <select id="country" v-model="form.country" :class="selectClass">
                                        <option v-for="c in countries()" :key="c.code" :value="c.code">{{ c.name }}</option>
                                    </select>
                                </div>
                                <BaseInput v-model="form.city" :label="t('workshopEditor.city')" :maxlength="120" :error="errors.location_city" />
                            </div>
                            <BaseInput v-model="form.address" :label="t('workshopEditor.address')" :maxlength="255" :hint="t('workshopEditor.addressHint')" :error="errors.location_address" />
                        </template>
                        <BaseInput v-else v-model="form.meeting_url" type="url" :label="t('workshopEditor.meetingUrl')" :maxlength="500" :hint="t('workshopEditor.meetingHint')" :error="errors.external_url" />
                        <BaseTextarea v-model="form.note" :label="t('workshopEditor.note')" :rows="3" :maxlength="2000" :hint="t('workshopEditor.noteHint')" :error="errors.participant_note" />
                        <div class="flex flex-col gap-2 md:max-w-[420px]">
                            <label for="tz" class="text-[15px] font-semibold text-ink">{{ t('workshopEditor.timezone') }}</label>
                            <select id="tz" v-model="form.timezone" :class="selectClass" aria-describedby="tz-hint">
                                <option v-for="z in zones" :key="z" :value="z">{{ z.replace('_', ' ') }}</option>
                            </select>
                            <p id="tz-hint" class="m-0 text-[13px] text-ink-muted">{{ t('workshopEditor.timezoneHint') }}</p>
                        </div>
                    </section>

                    <section class="flex flex-col gap-6" aria-labelledby="sec-price">
                        <h2 id="sec-price" class="m-0 text-xl font-semibold text-plum">{{ t('workshopEditor.priceSeats') }}</h2>
                        <div class="grid max-w-[620px] grid-cols-[minmax(0,1fr)_110px_minmax(0,1fr)] gap-4">
                            <BaseInput v-model="form.price" :label="t('workshopEditor.price')" placeholder="35" :error="errors.price_amount_minor" />
                            <div class="flex flex-col gap-2">
                                <label for="currency" class="text-[15px] font-semibold text-ink">{{ t('workshopEditor.currency') }}</label>
                                <select id="currency" v-model="form.currency" :class="selectClass">
                                    <option value="EUR">EUR</option>
                                    <option value="USD">USD</option>
                                    <option value="UAH">UAH</option>
                                </select>
                            </div>
                            <BaseInput v-model="form.capacity" :label="t('workshopEditor.capacity')" :error="errors.capacity" />
                        </div>
                        <p class="-mt-3 mb-0 text-[13px] text-ink-muted">
                            {{ t('workshopEditor.capacityHint') }}
                            <template v-if="hasBookings"> {{ t('workshopEditor.booked', { n: workshop?.seats_booked }) }}</template>
                        </p>
                    </section>

                    <section class="flex flex-col gap-5" aria-labelledby="sec-lang">
                        <ContentLanguage v-model="form.content_locale" />
                        <fieldset class="m-0 flex flex-col gap-5 rounded-card border border-line p-5" :lang="otherLocale">
                            <legend id="sec-lang" class="px-1 text-[15px] font-semibold text-plum">{{ t('content.translation', { lang: t(`content.langName.${otherLocale}`) }) }}</legend>
                            <p class="m-0 text-[13px] text-ink-muted">{{ t('content.translationHint') }}</p>
                            <BaseInput v-model="form.tr_name" :label="t('workshopEditor.name')" :maxlength="160" :error="errors[`translations.${otherLocale}.name`]" />
                            <BaseTextarea v-model="form.tr_description" :label="t('workshopEditor.description')" :rows="4" :maxlength="10000" :error="errors[`translations.${otherLocale}.description`]" />
                            <BaseTextarea v-model="form.tr_note" :label="t('workshopEditor.note')" :rows="2" :maxlength="2000" :error="errors[`translations.${otherLocale}.participant_note`]" />
                        </fieldset>
                    </section>
                </fieldset>

                <div v-if="formError" role="alert" class="rounded-[10px] border border-kalyna/30 bg-kalyna/5 px-3.5 py-3 text-[15px] text-kalyna">{{ formError }}</div>
                <p v-else-if="notice" role="status" class="m-0 text-[15px] font-semibold text-[#2F4A1C]">{{ notice }}</p>

                <div class="flex flex-wrap gap-3 border-t border-line pt-6">
                    <template v-if="editable">
                        <BaseButton type="submit" size="lg" :variant="status === 'draft' || status === 'rejected' ? 'secondary' : 'primary'" :loading="busy === 'save'" :disabled="!dirty && !isNew">
                            {{ isNew ? t('workshopEditor.createDraft') : t('workshopEditor.save') }}
                        </BaseButton>
                        <BaseButton v-if="!isNew && (status === 'draft' || status === 'rejected')" size="lg" :loading="busy === 'submit'" @click="submit">{{ t('workshopEditor.submit') }}</BaseButton>
                    </template>
                    <BaseButton v-if="status === 'published' && workshop" variant="ghost" :loading="busy === 'archive'" @click="lifecycle('archive')">{{ t('workshopEditor.archive') }}</BaseButton>
                    <BaseButton v-if="status === 'archived'" :loading="busy === 'relist'" @click="lifecycle('relist')">{{ t('workshopEditor.relist') }}</BaseButton>
                    <span class="flex-1" />
                    <template v-if="workshop?.is_deletable">
                        <template v-if="confirmDelete">
                            <span class="self-center text-[15px] text-ink">{{ t('workshopEditor.deleteConfirm') }}</span>
                            <BaseButton variant="secondary" :loading="busy === 'delete'" @click="destroy">{{ t('editor.yesDelete') }}</BaseButton>
                            <BaseButton variant="ghost" @click="confirmDelete = false">{{ t('editor.no') }}</BaseButton>
                        </template>
                        <BaseButton v-else variant="ghost" @click="confirmDelete = true">{{ t('workshopEditor.deleteDraft') }}</BaseButton>
                    </template>
                    <BaseButton v-if="(status === 'published' || status === 'archived') && !cancelOpen" variant="ghost" class="!text-kalyna" @click="openCancel">
                        {{ t('workshopEditor.cancelWorkshop') }}
                    </BaseButton>
                </div>
            </form>

            <form v-if="cancelOpen" id="cancel-form" class="mt-4 flex flex-col gap-3 rounded-card border border-kalyna/30 bg-kalyna/5 p-5" @submit.prevent="confirmCancel" @keydown.esc="cancelOpen = false">
                <BaseTextarea v-model="cancelReason" :label="t('workshopEditor.cancelReason')" :rows="3" :maxlength="500" :hint="t('workshopEditor.cancelHint')" :error="cancelError" required />
                <div class="flex flex-wrap gap-2">
                    <BaseButton type="submit" :loading="busy === 'cancel'">{{ t('workshopEditor.confirmCancel') }}</BaseButton>
                    <BaseButton variant="ghost" @click="cancelOpen = false">{{ t('workshopEditor.keep') }}</BaseButton>
                </div>
            </form>

            <p v-if="isNew" class="mt-10 mb-0 rounded-[10px] bg-lavender-50 px-4 py-3 text-[15px] text-plum">{{ t('workshopEditor.saveFirst') }}</p>

            <template v-else-if="workshop">
                <!-- Schedule -->
                <section class="mt-12 flex flex-col gap-4" aria-labelledby="sec-schedule">
                    <div>
                        <h2 id="sec-schedule" class="m-0 text-xl font-semibold text-plum">{{ t('workshopEditor.schedule') }}</h2>
                        <p class="mt-1 mb-0 text-[15px] text-ink-muted">{{ t('workshopEditor.scheduleHint', { zone: workshop.timezone.replace('_', ' ') }) }}</p>
                    </div>
                    <p v-if="!workshop.sessions.length" class="m-0 text-notice-ink">{{ t('workshopEditor.noSessions') }}</p>
                    <ol v-else class="m-0 flex list-none flex-col gap-3 p-0">
                        <li v-for="(s, i) in workshop.sessions" :key="s.uuid" class="flex flex-wrap items-end gap-3 rounded-card border border-line p-4">
                            <span class="w-full text-[13px] font-semibold text-ink-soft">{{ t('workshopEditor.session', { n: i + 1 }) }}</span>
                            <template v-if="sessionEdits[s.uuid]">
                                <label class="flex flex-col gap-1 text-[13px] text-ink-muted">
                                    {{ t('workshopEditor.start') }}
                                    <input v-model="sessionEdits[s.uuid]!.start" type="datetime-local" :disabled="!editable" :class="dateClass" />
                                </label>
                                <label class="flex flex-col gap-1 text-[13px] text-ink-muted">
                                    {{ t('workshopEditor.end') }}
                                    <input v-model="sessionEdits[s.uuid]!.end" type="datetime-local" :disabled="!editable" :class="dateClass" />
                                </label>
                            </template>
                            <template v-if="editable">
                                <BaseButton
                                    v-if="changed(s.uuid)"
                                    variant="secondary"
                                    :loading="busy === `s-${s.uuid}`"
                                    @click="schedule(`s-${s.uuid}`, () => workshopsApi.updateSession(s.uuid, sessionEdits[s.uuid]!.start, sessionEdits[s.uuid]!.end))"
                                >
                                    {{ t('workshopEditor.saveSession') }}
                                </BaseButton>
                                <button type="button" class="h-11 rounded-full px-3 text-sm text-kalyna hover:bg-kalyna/5" :disabled="!!busy" @click="schedule(`s-${s.uuid}`, () => workshopsApi.deleteSession(s.uuid))">
                                    {{ t('workshopEditor.delete') }}
                                </button>
                            </template>
                        </li>
                    </ol>
                    <form v-if="editable" class="flex flex-wrap items-end gap-3" @submit.prevent="addSession">
                        <label class="flex flex-col gap-1 text-[13px] text-ink-muted">
                            {{ t('workshopEditor.start') }}
                            <input v-model="newSession.start" type="datetime-local" :class="dateClass" required @change="suggestEnd(newSession)" />
                        </label>
                        <label class="flex flex-col gap-1 text-[13px] text-ink-muted">
                            {{ t('workshopEditor.end') }}
                            <input v-model="newSession.end" type="datetime-local" :class="dateClass" required />
                        </label>
                        <BaseButton type="submit" variant="secondary" :loading="busy === 'session-new'">{{ t('workshopEditor.addSession') }}</BaseButton>
                    </form>
                    <p v-if="sessionError" role="alert" class="m-0 text-sm text-kalyna">{{ sessionError }}</p>
                </section>

                <!-- Cover -->
                <section class="mt-12 flex flex-col gap-4" aria-labelledby="sec-cover">
                    <div>
                        <h2 id="sec-cover" class="m-0 text-xl font-semibold text-plum">{{ t('workshopEditor.cover') }}</h2>
                        <p class="mt-1 mb-0 text-[15px] text-ink-muted">{{ t('workshopEditor.coverHint') }}</p>
                    </div>
                    <ProductCover :uuid="workshop.uuid" :name="workshop.name" :src="workshop.cover_url" ratio="wide" class="max-w-[420px]" />
                    <label v-if="editable" class="flex h-[52px] w-fit cursor-pointer items-center rounded-full border-[1.5px] border-plum px-5 font-semibold text-plum hover:bg-lavender-50">
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" :disabled="!!busy" @change="onCover" />
                        {{ t('workshopEditor.uploadCover') }}
                    </label>
                </section>

                <!-- Participants -->
                <section v-if="seatsMeta" class="mt-12 flex flex-col gap-4" aria-labelledby="sec-people">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 id="sec-people" class="m-0 text-xl font-semibold text-plum">{{ t('workshopEditor.participants') }}</h2>
                            <p class="mt-1 mb-0 text-[15px] text-ink-muted">
                                {{ t('workshopEditor.seatsSummary', { confirmed: seatsMeta.seats_confirmed, capacity: seatsMeta.capacity }) }}
                                <template v-if="seatsMeta.seats_pending">, {{ t('workshopEditor.pendingSeats', { n: seatsMeta.seats_pending }) }}</template>
                            </p>
                        </div>
                        <BaseButton v-if="participants.length" variant="secondary" @click="copyEmails">{{ copied ? t('workshopEditor.copied') : t('workshopEditor.copyEmails') }}</BaseButton>
                    </div>
                    <p v-if="!participants.length" class="m-0 text-ink-muted">{{ t('workshopEditor.participantsEmpty') }}</p>
                    <div v-else class="overflow-x-auto rounded-card border border-line">
                        <table class="w-full min-w-[520px] border-collapse text-[15px]">
                            <thead>
                                <tr class="bg-lavender-50 text-left text-[13px] text-ink-soft">
                                    <th scope="col" class="px-4 py-2 font-semibold">{{ t('workshopEditor.colName') }}</th>
                                    <th scope="col" class="px-4 py-2 font-semibold">{{ t('workshopEditor.colEmail') }}</th>
                                    <th scope="col" class="px-4 py-2 text-right font-semibold">{{ t('workshopEditor.colSeats') }}</th>
                                    <th scope="col" class="px-4 py-2 font-semibold">{{ t('workshopEditor.colDate') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="p in participants" :key="p.uuid" class="border-t border-line">
                                    <td class="px-4 py-2.5">{{ p.name }}</td>
                                    <td class="px-4 py-2.5"><a :href="`mailto:${p.email}`" class="text-plum">{{ p.email }}</a></td>
                                    <td class="px-4 py-2.5 text-right">{{ p.seats }}</td>
                                    <td class="px-4 py-2.5 text-ink-muted">{{ formatDate(p.confirmed_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>
        </template>
    </div>
</template>
