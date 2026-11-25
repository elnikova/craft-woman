<script setup lang="ts">
import { onMounted, ref } from 'vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import StatusPill from '@/components/orders/StatusPill.vue';
import ProductCover from '@/components/products/ProductCover.vue';
import { ordersApi } from '@/api/orders';
import { toApiError } from '@/api/http';
import type { Order } from '@/types/order';
import { formatPrice, plural } from '@/utils/format';
import { formatDate } from '@/utils/orderStatus';

const orders = ref<Order[]>([]);
const page = ref(1);
const lastPage = ref(1);
const loading = ref(true);
const error = ref('');

async function load(p = 1) {
    loading.value = true;
    error.value = '';
    try {
        const res = await ordersApi.mine(p);
        orders.value = p === 1 ? res.data : [...orders.value, ...res.data];
        page.value = res.meta.current_page;
        lastPage.value = res.meta.last_page;
    } catch (e) {
        error.value = toApiError(e).message;
    } finally {
        loading.value = false;
    }
}

const items = (o: Order) => o.groups.flatMap((g) => g.items);
const count = (o: Order) => items(o).reduce((s, i) => s + i.quantity, 0);

onMounted(() => load());
</script>

<template>
    <div class="max-w-[1080px]">
        <h1 class="font-flair m-0 text-[40px] leading-tight font-bold tracking-[-0.015em] text-plum">Замовлення</h1>

        <div v-if="loading && orders.length === 0" class="mt-8 space-y-3" aria-busy="true" aria-label="Завантаження">
            <div v-for="i in 3" :key="i" class="h-24 animate-pulse rounded-card bg-lavender-50" />
        </div>

        <div v-else-if="error" role="alert" class="mt-8 flex flex-col items-start gap-4 rounded-card border border-kalyna/30 bg-kalyna/5 p-6">
            <p class="m-0 text-kalyna">{{ error }}</p>
            <BaseButton variant="secondary" @click="load()">Спробувати ще раз</BaseButton>
        </div>

        <div v-else-if="orders.length === 0" class="mt-10 flex flex-col items-start gap-4">
            <p class="m-0 text-lg text-ink-muted">Ви ще нічого не замовляли.</p>
            <BaseButton :to="{ name: 'catalog' }">Перейти до каталогу</BaseButton>
        </div>

        <template v-else>
            <ul class="mt-8 mb-0 flex list-none flex-col gap-3 p-0">
                <li v-for="o in orders" :key="o.uuid">
                    <RouterLink
                        :to="{ name: 'account-order', params: { number: o.order_number } }"
                        class="flex flex-wrap items-center gap-5 rounded-card border border-line p-4 text-inherit no-underline hover:border-plum"
                    >
                        <div class="flex -space-x-4">
                            <ProductCover
                                v-for="i in items(o).slice(0, 3)"
                                :key="i.uuid"
                                :uuid="i.product_uuid ?? i.uuid"
                                :name="i.name"
                                :src="i.image_url"
                                class="!w-14 rounded-[10px] ring-2 ring-white"
                            />
                        </div>
                        <div class="min-w-0 flex-[1_1_220px]">
                            <div class="font-semibold text-plum">№ {{ o.order_number }}</div>
                            <div class="mt-0.5 text-sm text-ink-muted">
                                {{ formatDate(o.placed_at) }} · {{ plural(count(o), ['товар', 'товари', 'товарів']) }}
                            </div>
                        </div>
                        <div class="w-28 text-right font-bold text-plum">{{ formatPrice(o.total.amount_minor, o.total.currency) }}</div>
                        <StatusPill :status="o.status" />
                    </RouterLink>
                </li>
            </ul>
            <BaseButton v-if="page < lastPage" variant="secondary" class="mt-6" :loading="loading" @click="load(page + 1)">Показати ще</BaseButton>
        </template>
    </div>
</template>
