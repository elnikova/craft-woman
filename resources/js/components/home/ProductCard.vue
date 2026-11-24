<script setup lang="ts">
import { ref } from 'vue';
import type { ProductCardData } from '@/types/catalog';
import { formatPrice } from '@/utils/format';

defineProps<{ product: ProductCardData }>();

// TODO: замінити на favorites store + API, коли з'явиться модуль Favorites
const favorite = ref(false);
</script>

<template>
    <article class="flex flex-col gap-3">
        <div
            class="relative aspect-square rounded-card"
            :class="product.cover.pattern"
            :style="{ backgroundColor: product.cover.color }"
        >
            <span
                v-if="product.isDigital"
                class="absolute top-3 left-3 rounded-lg bg-plum px-2.5 py-1.5 text-[13px] font-medium text-white"
            >
                Цифровий файл
            </span>
            <button
                type="button"
                class="absolute top-3 right-3 grid h-11 w-11 place-items-center rounded-full bg-white text-plum"
                :aria-pressed="favorite"
                :aria-label="favorite ? 'Прибрати з обраного' : 'Додати в обране'"
                @click="favorite = !favorite"
            >
                <svg width="20" height="20" viewBox="0 0 24 24" :fill="favorite ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z" />
                </svg>
            </button>
        </div>
        <RouterLink to="/" class="text-[17px] leading-snug font-semibold text-ink no-underline hover:underline hover:underline-offset-4">
            {{ product.name }}
        </RouterLink>
        <div class="text-sm text-ink-muted">{{ product.makerName }}</div>
        <div class="text-lg font-bold text-plum">{{ formatPrice(product.priceCents, product.currency) }}</div>
    </article>
</template>
