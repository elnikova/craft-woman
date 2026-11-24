<script setup lang="ts">
import type { GroupStatus, OrderStatus } from '@/types/order';
import { GROUP_STATUS_LABELS, ORDER_STATUS_LABELS, statusTone } from '@/utils/orderStatus';

const props = defineProps<{ status: OrderStatus | GroupStatus; kind?: 'order' | 'group' }>();
const label = () =>
    props.kind === 'group' ? GROUP_STATUS_LABELS[props.status as GroupStatus] : ORDER_STATUS_LABELS[props.status as OrderStatus];
</script>

<template>
    <span class="inline-flex h-7 items-center rounded-full px-3 text-[13px] font-semibold whitespace-nowrap" :class="statusTone(status)">
        {{ label() }}
    </span>
</template>
