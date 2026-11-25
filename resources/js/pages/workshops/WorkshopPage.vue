<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import MakerAvatar from '@/components/makers/MakerAvatar.vue';
import ProductCover from '@/components/products/ProductCover.vue';
import WorkshopStatusBadge from '@/components/workshops/WorkshopStatusBadge.vue';
import { workshopsApi } from '@/api/workshops';
import { toApiError } from '@/api/http';
import { countryName } from '@/utils/orderStatus';
import { useCartStore } from '@/stores/cart';
import type { WorkshopDetail } from '@/types/workshop';
import { formatPrice, plural } from '@/utils/format';
import { dateBlock, differsFromBrowser, downloadIcs, icsFor, sessionLabel, zoneName } from '@/utils/workshop';

const props = defineProps<{ slug: string }>();
const { t, locale } = useI18n();
const cart = useCartStore();

const w = ref<WorkshopDetail | null>(null);
const loading = ref(true);
const notFound = ref(false);
const error = ref('');
const seats = ref(1);
const adding = ref(false);
const added = ref(false);
const actionError = ref('');

const paragraphs = computed(() => (w.value?.description ?? '').split(/\n\s*\n/).filter((p) => p.trim()));
const inCart = computed(() => cart.cart.groups.some((g) => g.items.some((i) => i.kind === 'workshop' && i.product_uuid === w.value?.uuid)));
const first = computed(() => (w.value?.starts_at ? dateBlock(w.value.starts_at, w.value.timezone) : null));
const showLocal = computed(() => !!w.value?.starts_at && w.value.format === 'online' && differsFromBrowser(w.value.starts_at, w.value.timezone));
const place = computed(() =>
    // The format badge already says "online"
    !w.value || w.value.format === 'online' ? '' : [w.value.city, w.value.country_code ? countryName(w.value.country_code) : null].filter(Boolean).join(', '),
);
const closedReason = computed(() => {
    if (!w.value || w.value.status !== 'published') return '';
    if (w.value.has_ended) return t('workshopPage.ended');
    if (!w.value.is_bookable) return t('workshopPage.started');
    if (w.value.seats_left < 1) return t('workshopPage.full');
    return '';
});

async function load() {
    loading.value = true;
    notFound.value = false;
    error.value = '';
    try {
        w.value = await workshopsApi.show(props.slug);
        seats.value = 1;
    } catch (e) {
        const err = toApiError(e);
        if (err.status === 404) notFound.value = true;
        else error.value = err.message;
    } finally {
        loading.value = false;
    }
}

async function book() {
    if (!w.value) return;
    adding.value = true;
    actionError.value = '';
    try {
        await cart.addWorkshop(w.value.uuid, seats.value);
        added.value = true;
    } catch (e) {
        actionError.value = toApiError(e).message;
    } finally {
        adding.value = false;
    }
}

function calendar() {
    if (!w.value) return;
    downloadIcs(`${w.value.slug}.ics`, icsFor(w.value, w.value.sessions, window.location.href));
}

watch(() => props.slug, load, { immediate: true });
</script>

<template>
    <div v-if="loading" class="mx-auto grid max-w-[1280px] animate-pulse gap-10 px-6 py-12 md:grid-cols-2 md:px-8" aria-busy="true" :aria-label="t('common.loading')">
        <div class="aspect-video rounded-card bg-lavender-50" />
        <div class="space-y-4"><div class="h-12 rounded bg-lavender-50" /><div class="h-6 w-1/2 rounded bg-lavender-50" /></div>
    </div>

    <div v-else-if="notFound" class="mx-auto flex max-w-[640px] flex-col items-start gap-4 px-6 py-20">
        <h1 class="font-flair m-0 text-4xl font-bold text-plum">{{ t('workshopPage.notFoundTitle') }}</h1>
        <p class="m-0 text-lg text-ink-muted">{{ t('workshopPage.notFoundText') }}</p>
        <BaseButton :to="{ name: 'workshops' }">{{ t('workshopPage.all') }}</BaseButton>
    </div>

    <div v-else-if="error || !w" role="alert" class="mx-auto flex max-w-[640px] flex-col items-start gap-4 px-6 py-20">
        <p class="m-0 text-kalyna">{{ error }}</p>
        <BaseButton variant="secondary" @click="load">{{ t('common.retry') }}</BaseButton>
    </div>

    <template v-else>
        <div v-if="w.status === 'cancelled'" class="bg-kalyna/10">
            <p class="mx-auto my-0 max-w-[1280px] px-6 py-3 font-semibold text-kalyna md:px-8">{{ t('workshopPage.cancelled', { reason: w.cancel_reason }) }}</p>
        </div>
        <div v-else-if="w.status !== 'published'" class="bg-notice-bg">
            <div class="mx-auto flex max-w-[1280px] flex-wrap items-center gap-3 px-6 py-3 text-notice-ink md:px-8">
                <WorkshopStatusBadge :status="w.status" />
                <span>{{ t('workshopPage.unpublished') }}</span>
            </div>
        </div>

        <section class="tx-weave bg-lavender-50">
            <div class="mx-auto grid max-w-[1280px] items-center gap-10 px-6 py-12 md:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)] md:px-8">
                <ProductCover :uuid="w.uuid" :name="w.name" :src="w.cover_url" ratio="wide" />
                <div class="flex flex-col gap-4">
                    <RouterLink :to="{ name: 'workshops' }" class="text-[15px] text-plum">{{ t('workshopPage.all') }}</RouterLink>
                    <div v-if="first" class="flex items-end gap-3">
                        <span class="font-flair text-[56px] leading-none font-bold text-plum">{{ first.day }}</span>
                        <span class="pb-1.5 text-lg text-ink-soft">{{ first.month }}, {{ first.weekday }} · {{ first.time }}</span>
                    </div>
                    <h1 class="font-flair m-0 text-[34px] leading-tight font-bold tracking-[-0.015em] text-plum md:text-[42px]" :lang="w.content_locale">{{ w.name }}</h1>
                    <div class="flex flex-wrap gap-x-5 gap-y-1 text-[15px] text-ink-soft">
                        <span class="rounded-lg bg-white px-2.5 py-1 font-medium text-plum">{{ w.format === 'online' ? t('workshop.online') : t('workshop.offline') }}</span>
                        <span v-if="place" class="self-center">{{ place }}</span>
                        <span v-if="w.sessions_count > 1" class="self-center">{{ plural(w.sessions_count, 'sessions') }}</span>
                    </div>

                    <!-- Owner -->
                    <div v-if="w.is_owner" class="mt-2 flex flex-wrap gap-3">
                        <BaseButton :to="{ name: 'studio-workshop', params: { uuid: w.uuid } }">{{ t('workshopPage.edit') }}</BaseButton>
                    </div>

                    <!-- Participant -->
                    <div v-else-if="w.my_seats > 0" class="mt-2 flex flex-col gap-3 rounded-card bg-white p-5">
                        <p class="m-0 text-lg font-semibold text-[#2F4A1C]">✓ {{ t('workshopPage.booked', { seats: plural(w.my_seats, 'seats') }) }}</p>
                        <BaseButton variant="secondary" class="self-start" @click="calendar">{{ t('workshopPage.addToCalendar') }}</BaseButton>
                    </div>

                    <!-- Buyer -->
                    <div v-else-if="w.status === 'published'" class="mt-2 flex flex-col gap-3">
                        <div class="text-[32px] font-bold text-plum">{{ formatPrice(w.price.amount_minor, w.price.currency) }}</div>
                        <p v-if="closedReason" class="m-0 rounded-[10px] bg-white px-4 py-3 text-[15px] font-semibold text-ink">{{ closedReason }}</p>
                        <template v-else>
                            <p class="m-0 text-[15px]" :class="w.seats_left <= 3 ? 'font-semibold text-warning-ink' : 'text-ink-soft'">
                                {{ t('workshopPage.seatsLeft', { n: w.seats_left, capacity: w.capacity }) }}
                            </p>
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="flex h-[52px] items-center rounded-full border border-line-strong bg-white" role="group" :aria-label="t('workshopPage.seats')">
                                    <button type="button" class="h-full w-12 text-xl text-plum disabled:opacity-40" :aria-label="t('common.less')" :disabled="seats <= 1" @click="seats--">−</button>
                                    <span class="w-8 text-center font-semibold" aria-live="polite">{{ seats }}</span>
                                    <button type="button" class="h-full w-12 text-xl text-plum disabled:opacity-40" :aria-label="t('common.more')" :disabled="seats >= w.seats_left" @click="seats++">+</button>
                                </div>
                                <BaseButton v-if="!inCart" size="lg" :loading="adding" @click="book">{{ t('workshopPage.book') }}</BaseButton>
                                <BaseButton v-else size="lg" variant="secondary" :to="{ name: 'cart' }">{{ t('workshopPage.toCart') }}</BaseButton>
                            </div>
                            <p v-if="added" role="status" class="m-0 text-[15px]">{{ t('workshopPage.inCart') }}</p>
                        </template>
                    </div>
                    <p v-if="actionError" role="alert" class="m-0 text-[15px] text-kalyna">{{ actionError }}</p>
                </div>
            </div>
        </section>

        <div class="mx-auto grid max-w-[1280px] gap-14 px-6 py-14 md:px-8 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
            <div class="flex flex-col gap-12">
                <section v-if="paragraphs.length" aria-labelledby="about-title">
                    <h2 id="about-title" class="font-flair m-0 text-3xl font-bold text-plum">{{ t('workshopPage.about') }}</h2>
                    <p v-if="w.content_locale !== locale" class="mt-3 mb-0 text-sm text-ink-muted">{{ t('lang.contentNote') }}</p>
                    <div class="mt-4 flex max-w-[68ch] flex-col gap-3 text-[17px] leading-relaxed text-ink" :lang="w.content_locale">
                        <p v-for="(p, i) in paragraphs" :key="i" class="m-0">{{ p }}</p>
                    </div>
                </section>

                <!-- Only participants, the owner and admins get these -->
                <section v-if="w.private" class="flex flex-col gap-4 rounded-card border-2 border-plum/20 p-6" aria-labelledby="private-title">
                    <h2 id="private-title" class="m-0 text-xl font-semibold text-plum">{{ w.format === 'online' ? t('workshopPage.meetingLink') : t('workshopPage.address') }}</h2>
                    <template v-if="w.format === 'online'">
                        <a v-if="w.private.meeting_url" :href="w.private.meeting_url" target="_blank" rel="noopener noreferrer" class="text-lg font-semibold break-all text-plum">
                            {{ w.private.meeting_url }}
                        </a>
                        <p v-else class="m-0 text-ink-muted">{{ t('workshopPage.linkSoon') }}</p>
                    </template>
                    <p v-else class="m-0 text-lg text-ink">{{ [w.private.address, w.city].filter(Boolean).join(', ') }}</p>
                    <div v-if="w.private.note">
                        <h3 class="m-0 text-[15px] font-semibold text-ink-soft">{{ t('workshopPage.note') }}</h3>
                        <p class="mt-1 mb-0 whitespace-pre-line text-ink">{{ w.private.note }}</p>
                    </div>
                </section>
            </div>

            <aside class="flex flex-col gap-8">
                <section aria-labelledby="schedule-title">
                    <h2 id="schedule-title" class="font-flair m-0 text-3xl font-bold text-plum">{{ t('workshopPage.schedule') }}</h2>
                    <ol class="mt-4 mb-0 flex list-none flex-col gap-2 p-0">
                        <li v-for="s in w.sessions" :key="s.uuid" class="rounded-[12px] bg-lavender-50 px-4 py-3">
                            <div class="font-semibold text-ink">{{ sessionLabel(s, w.timezone) }}</div>
                            <div v-if="showLocal" class="text-sm text-ink-muted">{{ t('workshopPage.yourTime', { time: sessionLabel(s) }) }}</div>
                        </li>
                    </ol>
                    <p v-if="w.starts_at" class="mt-3 mb-0 text-sm text-ink-muted">{{ t('workshopPage.zoneNote', { zone: `${w.timezone.replace('_', ' ')}, ${zoneName(w.starts_at, w.timezone)}` }) }}</p>
                    <p v-if="!w.private && w.status === 'published'" class="mt-3 mb-0 text-sm text-ink-muted">
                        {{ w.format === 'online' ? t('workshopPage.onlineHint') : t('workshopPage.addressHint') }}
                    </p>
                </section>

                <RouterLink
                    v-if="w.maker"
                    :to="{ name: 'maker', params: { slug: w.maker.slug } }"
                    class="flex items-center gap-4 rounded-card bg-lavender-50 p-4 text-inherit no-underline hover:bg-lavender-100"
                >
                    <MakerAvatar :uuid="w.maker.uuid" :name="w.maker.display_name" :src="w.maker.avatar_url" shape="circle" class="w-14 shrink-0" />
                    <div>
                        <div class="text-sm text-ink-muted">{{ t('workshopPage.maker') }}</div>
                        <div class="font-semibold text-plum">{{ w.maker.display_name }}</div>
                        <div v-if="w.maker.city" class="text-sm text-ink-muted">{{ w.maker.city }}</div>
                    </div>
                </RouterLink>
            </aside>
        </div>
    </template>
</template>
