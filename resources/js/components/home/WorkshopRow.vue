<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { WorkshopRowData } from '@/types/catalog';
import { formatDay, formatPrice, plural } from '@/utils/format';
import BaseButton from '@/components/ui/BaseButton.vue';

const { t } = useI18n();
const props = defineProps<{ workshop: WorkshopRowData }>();

const date = computed(() => formatDay(props.workshop.startsAt));
const left = computed(() => props.workshop.capacity - props.workshop.booked);
const isFull = computed(() => left.value <= 0);

const seats = computed(() => {
    if (isFull.value) return t('workshop.full');
    if (left.value <= 4) return t('workshop.fewLeft', { seats: plural(left.value, 'seats') });
    return t('workshop.free', { left: left.value, capacity: props.workshop.capacity });
});
</script>

<template>
    <li class="flex flex-wrap items-center gap-6 border-b border-line py-6">
        <div class="w-[88px] shrink-0 text-center">
            <div class="font-flair text-[40px] leading-none font-bold text-plum">{{ date.day }}</div>
            <div class="mt-1 text-sm text-ink-muted">{{ date.month }}, {{ date.time }}</div>
        </div>
        <div class="min-w-0 flex-[1_1_320px]">
            <div class="text-xl font-semibold text-ink">{{ workshop.title }}</div>
            <div class="mt-1.5 text-[15px] text-ink-muted">{{ workshop.makerName }} · {{ workshop.place }}</div>
        </div>
        <div class="rounded-lg bg-lavender-50 px-3 py-2 text-sm font-medium text-plum">
            {{ workshop.mode === 'online' ? t('workshop.online') : t('workshop.offline') }}
        </div>
        <div class="w-[150px] text-[15px]" :class="!isFull && left <= 4 ? 'text-warning-ink' : 'text-ink-muted'">{{ seats }}</div>
        <div class="w-20 text-lg font-bold text-plum">{{ formatPrice(workshop.priceCents, workshop.currency) }}</div>
        <BaseButton v-if="!isFull" variant="secondary" :to="{ name: 'home' }">{{ t('workshop.book') }}</BaseButton>
        <BaseButton v-else variant="ghost" :to="{ name: 'home' }">{{ t('workshop.waitlist') }}</BaseButton>
    </li>
</template>
