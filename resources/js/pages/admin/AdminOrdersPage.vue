<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import StatusPill from '@/components/orders/StatusPill.vue';
import { ordersApi } from '@/api/orders';
import { toApiError } from '@/api/http';
import type { Order, OrderStatus } from '@/types/order';
import { formatPrice } from '@/utils/format';
import { ORDER_STATUS_LABELS, PAYMENT_LABELS, formatDate } from '@/utils/orderStatus';

const STATUSES = Object.keys(ORDER_STATUS_LABELS) as OrderStatus[];
const status = ref<OrderStatus | ''>('');
const q = ref('');
const orders = ref<Order[]>([]);
const total = ref(0);
const { t } = useI18n();
const loading = ref(true);
const error = ref('');
const expanded = ref<string | null>(null);

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await ordersApi.all({ status: status.value || undefined, q: q.value.trim() || undefined });
        orders.value = res.data;
        total.value = res.meta.total;
    } catch (e) {
        error.value = toApiError(e).message;
    } finally {
        loading.value = false;
    }
}

let timer: ReturnType<typeof setTimeout> | undefined;
watch(q, () => {
    clearTimeout(timer);
    timer = setTimeout(load, 300);
});
watch(status, load);
onMounted(load);
</script>

<template>
    <div class="max-w-[1080px]">
        <h1 class="font-flair m-0 text-[40px] leading-tight font-bold tracking-[-0.015em] text-plum">{{ t('adminOrders.title') }}</h1>

        <div class="mt-8 flex flex-wrap gap-3">
            <input
                v-model="q"
                type="search"
                :aria-label="t('adminOrders.search')"
                :placeholder="t('adminOrders.searchPlaceholder')"
                class="h-11 min-w-[260px] flex-1 rounded-full border border-line-strong bg-white px-4 text-[15px] focus:border-plum focus:outline-none"
            />
            <select v-model="status" :aria-label="t('adminOrders.status')" class="h-11 rounded-full border border-line-strong bg-white px-4 text-[15px]">
                <option value="">{{ t('adminOrders.allStatuses') }}</option>
                <option v-for="s in STATUSES" :key="s" :value="s">{{ ORDER_STATUS_LABELS[s] }}</option>
            </select>
        </div>

        <div v-if="loading" class="mt-8 space-y-2" aria-busy="true" :aria-label="t('common.loading')">
            <div v-for="i in 5" :key="i" class="h-14 animate-pulse rounded-[12px] bg-lavender-50" />
        </div>
        <div v-else-if="error" role="alert" class="mt-8 flex flex-col items-start gap-4 rounded-card border border-kalyna/30 bg-kalyna/5 p-6">
            <p class="m-0 text-kalyna">{{ error }}</p>
            <BaseButton variant="secondary" @click="load">{{ t('common.retry') }}</BaseButton>
        </div>
        <p v-else-if="orders.length === 0" class="mt-10 text-lg text-ink-muted">{{ t('adminOrders.empty') }}</p>

        <template v-else>
            <p class="mt-6 mb-2 text-sm text-ink-muted">{{ t('common.found', { n: total }) }}</p>
            <table class="w-full border-collapse text-[15px]">
                <thead>
                    <tr class="border-b border-line-strong text-left text-sm text-ink-muted">
                        <th class="py-2 pr-3 font-semibold">{{ t('adminOrders.colNumber') }}</th>
                        <th class="py-2 pr-3 font-semibold">{{ t('adminOrders.colDate') }}</th>
                        <th class="py-2 pr-3 font-semibold">{{ t('adminOrders.colBuyer') }}</th>
                        <th class="py-2 pr-3 font-semibold">{{ t('adminOrders.colPayment') }}</th>
                        <th class="py-2 pr-3 text-right font-semibold">{{ t('adminOrders.colTotal') }}</th>
                        <th class="py-2 font-semibold">{{ t('adminOrders.colStatus') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="o in orders" :key="o.uuid">
                        <tr class="border-b border-line">
                            <td class="py-3 pr-3">
                                <button
                                    type="button"
                                    class="font-semibold text-plum underline-offset-2 hover:underline"
                                    :aria-expanded="expanded === o.uuid"
                                    @click="expanded = expanded === o.uuid ? null : o.uuid"
                                >
                                    {{ o.order_number }}
                                </button>
                            </td>
                            <td class="py-3 pr-3 whitespace-nowrap">{{ formatDate(o.placed_at) }}</td>
                            <td class="py-3 pr-3">{{ o.billing_email }}</td>
                            <td class="py-3 pr-3">{{ PAYMENT_LABELS[o.payment_method] }}<span v-if="o.is_paid" class="text-ink-muted"> ✓</span></td>
                            <td class="py-3 pr-3 text-right font-semibold">{{ formatPrice(o.total.amount_minor, o.total.currency) }}</td>
                            <td class="py-3"><StatusPill :status="o.status" /></td>
                        </tr>
                        <tr v-if="expanded === o.uuid" class="border-b border-line bg-lavender-50/60">
                            <td colspan="6" class="px-3 py-3">
                                <div v-for="g in o.groups" :key="g.uuid" class="flex flex-wrap items-center gap-3 py-1">
                                    <span class="w-48 font-semibold">{{ g.maker.name }}</span>
                                    <span class="flex-1 text-ink-muted">{{ g.items.map((i) => `${i.name} × ${i.quantity}`).join(', ') }}</span>
                                    <span>{{ formatPrice(g.total.amount_minor, g.total.currency) }}</span>
                                    <StatusPill :status="g.status" kind="group" />
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </template>
    </div>
</template>
