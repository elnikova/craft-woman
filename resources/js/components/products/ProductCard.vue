<script setup lang="ts">
import { computed } from 'vue';
import type { Product } from '@/types/product';
import { formatPrice } from '@/utils/format';
import ProductCover from './ProductCover.vue';
import FavoriteButton from './FavoriteButton.vue';

const props = defineProps<{ product: Product }>();

/** «від …», якщо у варіантів різні ціни */
const priceLabel = computed(() => {
    const prices = props.product.variants.map((v) => v.price.amount_minor);
    const min = prices.length ? Math.min(...prices) : props.product.price.amount_minor;
    const from = prices.length > 1 && new Set(prices).size > 1;
    return `${from ? 'від ' : ''}${formatPrice(min, props.product.price.currency)}`;
});

const soldOut = computed(() => !props.product.is_digital && props.product.variants.length > 0 && props.product.variants.every((v) => !v.in_stock));
</script>

<template>
    <article class="flex flex-col gap-3">
        <div class="relative">
            <RouterLink :to="{ name: 'product', params: { slug: product.slug } }" tabindex="-1" aria-hidden="true">
                <ProductCover :uuid="product.uuid" :name="product.name" :src="product.images[0]?.url" />
            </RouterLink>
            <span v-if="product.is_digital" class="absolute top-3 left-3 rounded-lg bg-plum px-2.5 py-1.5 text-[13px] font-medium text-white">
                Цифровий файл
            </span>
            <span v-else-if="soldOut" class="absolute top-3 left-3 rounded-lg bg-white px-2.5 py-1.5 text-[13px] font-medium text-ink">
                Розпродано
            </span>
            <FavoriteButton :uuid="product.uuid" :name="product.name" class="absolute top-3 right-3" />
        </div>
        <RouterLink
            :to="{ name: 'product', params: { slug: product.slug } }"
            class="text-[17px] leading-snug font-semibold text-ink no-underline hover:underline hover:underline-offset-4"
        >
            {{ product.name }}
        </RouterLink>
        <RouterLink v-if="product.maker" :to="{ name: 'maker', params: { slug: product.maker.slug } }" class="text-sm text-ink-muted no-underline hover:text-plum">
            {{ product.maker.display_name }}
        </RouterLink>
        <div class="text-lg font-bold text-plum">{{ priceLabel }}</div>
    </article>
</template>
