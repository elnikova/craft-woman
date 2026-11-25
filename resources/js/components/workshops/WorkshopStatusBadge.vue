<script setup lang="ts">
import type { WorkshopStatus } from '@/types/workshop';
import { WORKSHOP_STATUS_LABELS, WORKSHOP_STATUS_TONE } from '@/utils/workshop';

defineProps<{ status: WorkshopStatus }>();
</script>

<template>
    <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold whitespace-nowrap" :class="WORKSHOP_STATUS_TONE[status]">
        {{ WORKSHOP_STATUS_LABELS[status] }}
    </span>
</template>
