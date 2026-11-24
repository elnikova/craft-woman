<script setup lang="ts">
import type { ProductStatus } from '@/types/product';
import { PRODUCT_STATUS_LABELS, PRODUCT_STATUS_TONE } from '@/utils/productStatus';

defineProps<{ status: ProductStatus }>();
</script>

<template>
    <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold whitespace-nowrap" :class="PRODUCT_STATUS_TONE[status]">
        {{ PRODUCT_STATUS_LABELS[status] }}
    </span>
</template>
