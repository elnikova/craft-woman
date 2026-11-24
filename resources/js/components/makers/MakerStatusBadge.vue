<script setup lang="ts">
import { computed } from 'vue';
import type { MakerStatus } from '@/types/maker';
import { MAKER_STATUS_LABELS } from '@/utils/makerStatus';

const props = defineProps<{ status: MakerStatus }>();


const tone = computed(
    () =>
        ({
            draft: 'bg-lavender-50 text-plum',
            pending_review: 'bg-notice-bg text-notice-ink',
            approved: 'bg-[#E3EDD8] text-[#2F4A1C]',
            rejected: 'bg-kalyna/10 text-kalyna',
            suspended: 'bg-ink/10 text-ink',
        })[props.status],
);
</script>

<template>
    <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold" :class="tone">
        {{ MAKER_STATUS_LABELS[status] }}
    </span>
</template>
