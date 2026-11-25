<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { WorkshopCard } from '@/types/workshop';
import { formatPrice, plural } from '@/utils/format';
import { dateBlock } from '@/utils/workshop';

const { t } = useI18n();
const props = defineProps<{ workshop: WorkshopCard }>();

const date = computed(() => (props.workshop.starts_at ? dateBlock(props.workshop.starts_at, props.workshop.timezone) : null));
const left = computed(() => props.workshop.seats_left);
const seats = computed(() => {
    if (left.value <= 0) return t('workshop.full');
    if (left.value <= 3) return t('workshop.fewLeft', { seats: plural(left.value, 'seats') });
    return t('workshop.free', { left: left.value, capacity: props.workshop.capacity });
});
const place = computed(() => (props.workshop.format === 'online' ? t('workshop.online') : props.workshop.city));
</script>

<template>
    <li class="relative flex flex-wrap items-center gap-x-6 gap-y-3 border-b border-line py-6">
        <div v-if="date" class="w-[88px] shrink-0 text-center">
            <div class="font-flair text-[40px] leading-none font-bold text-plum">{{ date.day }}</div>
            <div class="mt-1 text-sm text-ink-muted">{{ date.month }}, {{ date.time }}</div>
        </div>
        <div class="min-w-0 flex-[1_1_320px]">
            <RouterLink
                :to="{ name: 'workshop', params: { slug: workshop.slug } }"
                class="text-xl font-semibold text-ink no-underline after:absolute after:inset-0 hover:text-plum hover:underline"
                :lang="workshop.content_locale"
            >
                {{ workshop.name }}
            </RouterLink>
            <div class="mt-1.5 text-[15px] text-ink-muted">
                {{ [workshop.maker?.display_name, place, workshop.sessions_count > 1 ? plural(workshop.sessions_count, 'sessions') : null].filter(Boolean).join(' · ') }}
            </div>
        </div>
        <div class="rounded-lg bg-lavender-50 px-3 py-2 text-sm font-medium text-plum">
            {{ workshop.format === 'online' ? t('workshop.online') : t('workshop.offline') }}
        </div>
        <div class="w-[160px] text-[15px]" :class="left > 0 && left <= 3 ? 'font-semibold text-warning-ink' : 'text-ink-muted'">{{ seats }}</div>
        <div class="w-20 text-lg font-bold text-plum">{{ formatPrice(workshop.price.amount_minor, workshop.price.currency) }}</div>
    </li>
</template>
