<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import { workshopsApi } from '@/api/workshops';
import { toApiError } from '@/api/http';
import type { Booking } from '@/types/workshop';
import { plural } from '@/utils/format';
import { dateBlock, differsFromBrowser, downloadIcs, icsFor, sessionLabel } from '@/utils/workshop';

const { t } = useI18n();
const items = ref<Booking[]>([]);
const loading = ref(true);
const error = ref('');

const upcoming = computed(() =>
    items.value
        .filter((b) => b.status === 'confirmed' && !b.workshop.has_ended)
        .sort((a, b) => (a.workshop.starts_at ?? '').localeCompare(b.workshop.starts_at ?? '')),
);
const past = computed(() => items.value.filter((b) => !upcoming.value.includes(b)));

async function load() {
    loading.value = true;
    error.value = '';
    try {
        items.value = await workshopsApi.bookings();
    } catch (e) {
        error.value = toApiError(e).message;
    } finally {
        loading.value = false;
    }
}

function calendar(b: Booking) {
    const url = new URL(`/workshops/${b.workshop.slug}`, window.location.origin).toString();
    downloadIcs(`${b.workshop.slug}.ics`, icsFor(b.workshop, b.workshop.sessions, url));
}

onMounted(load);
</script>

<template>
    <div class="max-w-[1080px]">
        <h1 class="font-flair m-0 text-[40px] leading-tight font-bold tracking-[-0.015em] text-plum">{{ t('bookings.title') }}</h1>

        <div v-if="loading" class="mt-8 space-y-3" aria-busy="true" :aria-label="t('common.loading')">
            <div v-for="i in 2" :key="i" class="h-40 animate-pulse rounded-card bg-lavender-50" />
        </div>
        <div v-else-if="error" role="alert" class="mt-8 flex flex-col items-start gap-4 rounded-card border border-kalyna/30 bg-kalyna/5 p-6">
            <p class="m-0 text-kalyna">{{ error }}</p>
            <BaseButton variant="secondary" @click="load">{{ t('common.retry') }}</BaseButton>
        </div>
        <div v-else-if="items.length === 0" class="mt-10 flex flex-col items-start gap-4">
            <p class="m-0 text-lg text-ink-muted">{{ t('bookings.empty') }}</p>
            <BaseButton :to="{ name: 'workshops' }">{{ t('bookings.browse') }}</BaseButton>
        </div>

        <template v-else>
            <section v-if="upcoming.length" class="mt-8" aria-labelledby="up-title">
                <h2 id="up-title" class="m-0 text-xl font-semibold text-plum">{{ t('bookings.upcoming') }}</h2>
                <ul class="mt-4 mb-0 flex list-none flex-col gap-4 p-0">
                    <li v-for="b in upcoming" :key="b.uuid" class="flex flex-wrap gap-6 rounded-card border border-line bg-white p-5">
                        <div v-if="b.workshop.starts_at" class="w-[80px] shrink-0 text-center">
                            <div class="font-flair text-[40px] leading-none font-bold text-plum">{{ dateBlock(b.workshop.starts_at, b.workshop.timezone).day }}</div>
                            <div class="mt-1 text-sm text-ink-muted">{{ dateBlock(b.workshop.starts_at, b.workshop.timezone).month }}</div>
                        </div>
                        <div class="min-w-0 flex-[1_1_320px]">
                            <RouterLink :to="{ name: 'workshop', params: { slug: b.workshop.slug } }" class="text-lg font-semibold text-plum no-underline hover:underline" :lang="b.workshop.content_locale">
                                {{ b.workshop.name }}
                            </RouterLink>
                            <div class="text-sm text-ink-muted">{{ b.workshop.maker?.display_name }} · {{ plural(b.seats, 'seats') }}</div>
                            <ul class="mt-3 mb-0 list-none p-0 text-[15px] text-ink">
                                <li v-for="s in b.workshop.sessions" :key="s.uuid">
                                    {{ sessionLabel(s, b.workshop.timezone) }}
                                    <span v-if="b.workshop.format === 'online' && differsFromBrowser(s.starts_at, b.workshop.timezone)" class="text-ink-muted">
                                        ({{ t('workshopPage.yourTime', { time: sessionLabel(s) }) }})
                                    </span>
                                </li>
                            </ul>
                            <div v-if="b.workshop.private" class="mt-3 text-[15px]">
                                <template v-if="b.workshop.format === 'online'">
                                    <a v-if="b.workshop.private.meeting_url" :href="b.workshop.private.meeting_url" target="_blank" rel="noopener noreferrer" class="font-semibold break-all text-plum">
                                        {{ t('workshopPage.join') }}: {{ b.workshop.private.meeting_url }}
                                    </a>
                                    <span v-else class="text-ink-muted">{{ t('workshopPage.linkSoon') }}</span>
                                </template>
                                <span v-else class="text-ink">{{ t('workshopPage.address') }}: {{ [b.workshop.private.address, b.workshop.city].filter(Boolean).join(', ') }}</span>
                                <p v-if="b.workshop.private.note" class="mt-2 mb-0 whitespace-pre-line text-ink-soft">{{ b.workshop.private.note }}</p>
                            </div>
                        </div>
                        <div class="flex flex-col items-start gap-2">
                            <BaseButton variant="secondary" @click="calendar(b)">{{ t('workshopPage.addToCalendar') }}</BaseButton>
                        </div>
                    </li>
                </ul>
            </section>

            <section v-if="past.length" class="mt-10" aria-labelledby="past-title">
                <h2 id="past-title" class="m-0 text-xl font-semibold text-plum">{{ t('bookings.past') }}</h2>
                <ul class="mt-4 mb-0 flex list-none flex-col gap-2 p-0">
                    <li v-for="b in past" :key="b.uuid" class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-card bg-lavender-50 px-5 py-3">
                        <RouterLink :to="{ name: 'workshop', params: { slug: b.workshop.slug } }" class="font-semibold text-ink no-underline hover:text-plum">{{ b.workshop.name }}</RouterLink>
                        <span v-if="b.workshop.starts_at" class="text-sm text-ink-muted">{{ sessionLabel(b.workshop.sessions[0] ?? { starts_at: b.workshop.starts_at, ends_at: b.workshop.ends_at ?? b.workshop.starts_at }, b.workshop.timezone) }}</span>
                        <span v-if="b.status === 'cancelled'" class="text-sm font-semibold text-kalyna">{{ t('bookings.cancelledBy', { reason: b.cancel_reason }) }}</span>
                    </li>
                </ul>
            </section>
        </template>
    </div>
</template>
