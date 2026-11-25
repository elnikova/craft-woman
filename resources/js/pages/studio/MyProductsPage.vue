<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import ProductCover from '@/components/products/ProductCover.vue';
import ProductStatusBadge from '@/components/products/ProductStatusBadge.vue';
import { productsApi } from '@/api/products';
import { toApiError } from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import type { Product, ProductStatus } from '@/types/product';
import { formatPrice } from '@/utils/format';
import { PRODUCT_STATUS_LABELS } from '@/utils/productStatus';

const auth = useAuthStore();
const canSell = computed(() => auth.user?.maker_status === 'approved');

const TABS: (ProductStatus | 'all')[] = ['all', 'draft', 'pending_review', 'published', 'rejected', 'archived'];
const { t } = useI18n();
const tab = ref<ProductStatus | 'all'>('all');
const items = ref<Product[]>([]);
const loading = ref(true);
const error = ref('');

async function load() {
    loading.value = true;
    error.value = '';
    try {
        items.value = (await productsApi.mine(tab.value === 'all' ? undefined : tab.value)).data;
    } catch (e) {
        error.value = toApiError(e).message;
    } finally {
        loading.value = false;
    }
}

function totalStock(p: Product): string {
    if (p.is_digital) return t('myProducts.file');
    const sum = p.variants.filter((v) => v.status !== 'inactive').reduce((s, v) => s + (v.stock ?? 0), 0);
    return sum === 0 ? t('myProducts.none') : t('myProducts.pcs', { n: sum });
}

watch(tab, load);
onMounted(load);
</script>

<template>
    <div class="max-w-[1080px]">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <h1 class="font-flair m-0 text-[40px] leading-tight font-bold tracking-[-0.015em] text-plum">{{ t('myProducts.title') }}</h1>
            <BaseButton v-if="canSell" :to="{ name: 'studio-product-new' }">{{ t('myProducts.add') }}</BaseButton>
        </div>

        <div v-if="!canSell" class="mt-6 rounded-card bg-notice-bg p-5 text-notice-ink">
            <p class="m-0 font-semibold">{{ t('myProducts.notApproved') }}</p>
            <p class="mt-1 mb-0">
                <RouterLink :to="{ name: 'studio' }" class="font-semibold text-notice-ink">{{ t('myProducts.checkProfile') }}</RouterLink>
            </p>
        </div>

        <div role="tablist" :aria-label="t('myProducts.statusTabs')" class="mt-8 flex flex-wrap gap-2">
            <button
                v-for="tabKey in TABS"
                :key="tabKey"
                type="button"
                role="tab"
                :aria-selected="tab === tabKey"
                class="h-11 rounded-full border px-[18px] text-[15px]"
                :class="tab === tabKey ? 'border-plum bg-plum text-white' : 'border-line-strong bg-white text-ink hover:border-plum'"
                @click="tab = tabKey"
            >
                {{ tabKey === 'all' ? t('myProducts.all') : PRODUCT_STATUS_LABELS[tabKey] }}
            </button>
        </div>

        <div v-if="loading" class="mt-8 space-y-3" aria-busy="true" :aria-label="t('common.loading')">
            <div v-for="i in 4" :key="i" class="h-24 animate-pulse rounded-card bg-lavender-50" />
        </div>

        <div v-else-if="error" role="alert" class="mt-8 flex flex-col items-start gap-4 rounded-card border border-kalyna/30 bg-kalyna/5 p-6">
            <p class="m-0 text-kalyna">{{ error }}</p>
            <BaseButton variant="secondary" @click="load">{{ t('common.retry') }}</BaseButton>
        </div>

        <div v-else-if="items.length === 0" class="mt-10 flex flex-col items-start gap-4">
            <p class="m-0 text-lg text-ink-muted">
                {{ tab === 'all' ? t('myProducts.empty') : t('myProducts.emptyStatus', { status: PRODUCT_STATUS_LABELS[tab] }) }}
            </p>
            <BaseButton v-if="canSell && tab === 'all'" :to="{ name: 'studio-product-new' }">{{ t('myProducts.addFirst') }}</BaseButton>
        </div>

        <ul v-else class="mt-8 mb-0 flex list-none flex-col gap-3 p-0">
            <li v-for="p in items" :key="p.uuid">
                <RouterLink
                    :to="{ name: 'studio-product', params: { uuid: p.uuid } }"
                    class="flex flex-wrap items-center gap-5 rounded-card border border-line p-4 text-inherit no-underline hover:border-plum"
                >
                    <ProductCover :uuid="p.uuid" :name="p.name" :src="p.images[0]?.url" class="!w-20 shrink-0" />
                    <div class="min-w-0 flex-[1_1_240px]">
                        <div class="text-lg font-semibold text-plum">{{ p.name }}</div>
                        <div class="mt-1 text-sm text-ink-muted">{{ p.category?.name ?? t('myProducts.noCategory') }} · {{ p.is_digital ? t('myProducts.digital') : t('myProducts.physical') }}</div>
                        <p v-if="p.status === 'rejected' && p.review_note" class="mt-2 mb-0 text-sm text-kalyna">{{ t('myProducts.note', { note: p.review_note }) }}</p>
                    </div>
                    <div class="w-24 text-right text-[15px] text-ink-muted">{{ totalStock(p) }}</div>
                    <div class="w-24 text-right font-bold text-plum">{{ formatPrice(p.price.amount_minor, p.price.currency) }}</div>
                    <ProductStatusBadge v-if="p.status" :status="p.status" />
                </RouterLink>
            </li>
        </ul>
    </div>
</template>
