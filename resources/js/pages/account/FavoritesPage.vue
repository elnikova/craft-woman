<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import ProductCard from '@/components/products/ProductCard.vue';
import { favoritesApi } from '@/api/favorites';
import { toApiError } from '@/api/http';
import type { Product } from '@/types/product';

// Un-hearted products stay on the page until reload, so an accidental removal is easy to undo
const { t } = useI18n();
const products = ref<Product[]>([]);
const page = ref(1);
const lastPage = ref(1);
const loading = ref(true);
const error = ref('');

async function load(p = 1) {
    loading.value = true;
    error.value = '';
    try {
        const res = await favoritesApi.list(p);
        products.value = p === 1 ? res.data : [...products.value, ...res.data];
        page.value = res.meta.current_page;
        lastPage.value = res.meta.last_page;
    } catch (e) {
        error.value = toApiError(e).message;
    } finally {
        loading.value = false;
    }
}

onMounted(() => load());
</script>

<template>
    <div class="max-w-[1080px]">
        <h1 class="font-flair m-0 text-[40px] leading-tight font-bold tracking-[-0.015em] text-plum">{{ t('favorites.title') }}</h1>

        <div v-if="loading && products.length === 0" class="mt-8 grid grid-cols-2 gap-6 md:grid-cols-3" aria-busy="true" :aria-label="t('common.loading')">
            <div v-for="i in 6" :key="i" class="aspect-square animate-pulse rounded-card bg-lavender-50" />
        </div>

        <div v-else-if="error" role="alert" class="mt-8 flex flex-col items-start gap-4 rounded-card border border-kalyna/30 bg-kalyna/5 p-6">
            <p class="m-0 text-kalyna">{{ error }}</p>
            <BaseButton variant="secondary" @click="load()">{{ t('common.retry') }}</BaseButton>
        </div>

        <div v-else-if="products.length === 0" class="mt-10 flex flex-col items-start gap-4">
            <p class="m-0 text-lg text-ink-muted">{{ t('favorites.empty') }}</p>
            <BaseButton :to="{ name: 'catalog' }">{{ t('common.toCatalog') }}</BaseButton>
        </div>

        <template v-else>
            <div class="mt-8 grid grid-cols-2 gap-x-6 gap-y-10 md:grid-cols-3">
                <ProductCard v-for="p in products" :key="p.uuid" :product="p" />
            </div>
            <BaseButton v-if="page < lastPage" variant="secondary" class="mt-8" :loading="loading" @click="load(page + 1)">{{ t('common.showMore') }}</BaseButton>
        </template>
    </div>
</template>
