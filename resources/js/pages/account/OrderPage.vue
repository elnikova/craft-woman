<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import BaseButton from '@/components/ui/BaseButton.vue';
import StatusPill from '@/components/orders/StatusPill.vue';
import ProductCover from '@/components/products/ProductCover.vue';
import { ordersApi, type OrderWithDownloads } from '@/api/orders';
import { toApiError } from '@/api/http';
import { formatFileSize, formatPrice } from '@/utils/format';
import { countryName, PAYMENT_LABELS, formatDate } from '@/utils/orderStatus';
import { dayLabel } from '@/utils/workshop';

const props = defineProps<{ number: string }>();
const route = useRoute();
const { t } = useI18n();

const order = ref<OrderWithDownloads | null>(null);
const loading = ref(true);
const error = ref('');
const actionError = ref('');
const busy = ref<'pay' | 'cancel' | null>(null);
const justPlaced = computed(() => route.query.placed === '1');

/** Test payment: local development only, until Stripe is connected */
const canTestPay = import.meta.env.DEV;

const canCancel = computed(() => {
    const o = order.value;
    if (!o || o.status === 'cancelled') return false;
    const early = o.groups.every((g) => g.status === 'pending_payment' || g.status === 'awaiting_shipment' || g.status === 'cancelled');
    return early && (o.status === 'pending_payment' || o.payment_method === 'cash_on_delivery');
});


async function load() {
    loading.value = true;
    error.value = '';
    try {
        order.value = await ordersApi.show(props.number);
    } catch (e) {
        error.value = toApiError(e).message;
    } finally {
        loading.value = false;
    }
}

async function act(kind: 'pay' | 'cancel') {
    if (kind === 'cancel' && !window.confirm(t('order.cancelConfirm'))) return;
    busy.value = kind;
    actionError.value = '';
    try {
        order.value = kind === 'pay' ? await ordersApi.testPayment(props.number) : await ordersApi.cancel(props.number);
    } catch (e) {
        actionError.value = toApiError(e).message;
    } finally {
        busy.value = null;
    }
}

onMounted(load);
</script>

<template>
    <div class="max-w-[900px]">
        <RouterLink :to="{ name: 'account-orders' }" class="text-[15px] text-plum">{{ t('order.all') }}</RouterLink>

        <div v-if="loading" class="mt-6 h-64 animate-pulse rounded-card bg-lavender-50" aria-busy="true" :aria-label="t('common.loading')" />

        <div v-else-if="error || !order" role="alert" class="mt-6 rounded-card border border-kalyna/30 bg-kalyna/5 p-6 text-kalyna">
            {{ error || t('order.notFound') }}
        </div>

        <template v-else>
            <div v-if="justPlaced" role="status" class="mt-6 rounded-card bg-[#E6F0DC] p-5 text-[#2F4A1C]">
                <p class="m-0 font-semibold">{{ t('order.placed') }}</p>
                <p class="mt-1 mb-0">
                    {{
                        order.payment_method === 'online' && !order.is_paid
                            ? t('order.payNext')
                            : t('order.makersSee')
                    }}
                </p>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="font-flair m-0 text-[36px] leading-tight font-bold tracking-[-0.015em] text-plum">{{ t('orders.number', { number: order.order_number }) }}</h1>
                    <p class="mt-1 mb-0 text-ink-muted">
                        {{ formatDate(order.placed_at) }} · {{ PAYMENT_LABELS[order.payment_method] }} ·
                        {{ order.is_paid ? t('order.paid') : t('order.unpaid') }}
                    </p>
                </div>
                <StatusPill :status="order.status" />
            </div>

            <div v-if="(order.status === 'pending_payment' && canTestPay) || canCancel" class="mt-6 flex flex-wrap items-center gap-3">
                <BaseButton v-if="order.status === 'pending_payment' && canTestPay" :loading="busy === 'pay'" @click="act('pay')">{{ t('order.testPay') }}</BaseButton>
                <BaseButton v-if="canCancel" variant="secondary" :loading="busy === 'cancel'" @click="act('cancel')">{{ t('order.cancel') }}</BaseButton>
                <p v-if="order.status === 'pending_payment' && canTestPay" class="m-0 w-full text-sm text-ink-muted">
                    {{ t('order.testPayNote') }}
                </p>
            </div>
            <p v-if="actionError" role="alert" class="mt-3 mb-0 text-kalyna">{{ actionError }}</p>

            <section
                v-for="g in order.groups"
                :key="g.uuid"
                class="mt-6 rounded-card border border-line bg-white"
                :aria-label="t('order.partFrom', { maker: g.maker.name })"
            >
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3">
                    <span class="font-semibold text-plum">{{ g.maker.name }}</span>
                    <StatusPill :status="g.status" kind="group" />
                </header>

                <ul class="m-0 list-none p-0">
                    <li v-for="i in g.items" :key="i.uuid" class="flex flex-wrap items-center gap-4 border-b border-line px-5 py-4 last:border-b-0">
                        <ProductCover :uuid="i.product_uuid ?? i.course_uuid ?? i.workshop_uuid ?? i.uuid" :name="i.name" :src="i.image_url" class="!w-16 shrink-0" />
                        <div class="min-w-0 flex-[1_1_200px]">
                            <RouterLink v-if="i.slug" :to="{ name: i.is_workshop ? 'workshop' : i.is_course ? 'course' : 'product', params: { slug: i.slug } }" class="font-semibold text-ink no-underline hover:text-plum">
                                {{ i.name }}
                            </RouterLink>
                            <span v-else class="font-semibold">{{ i.name }}</span>
                            <div class="text-sm text-ink-muted">
                                {{
                                    [i.is_workshop ? t('cartWorkshop.line', { date: i.starts_at ? dayLabel(i.starts_at) : '' }) : i.is_course ? t('courses.onlineCourse') : i.is_digital ? t('common.digitalFile') : i.variant_name, `${i.quantity} × ${formatPrice(i.unit_price.amount_minor, i.unit_price.currency)}`]
                                        .filter(Boolean)
                                        .join(' · ')
                                }}
                            </div>
                            <ul v-if="order.downloads[i.uuid]?.length" class="mt-2 mb-0 flex list-none flex-wrap gap-2 p-0">
                                <li v-for="f in order.downloads[i.uuid]" :key="f.uuid">
                                    <a
                                        :href="ordersApi.downloadUrl(order.order_number, i.uuid, f.uuid)"
                                        class="inline-flex h-9 items-center gap-2 rounded-full bg-lavender-50 px-4 text-sm font-semibold text-plum no-underline hover:bg-lavender-100"
                                        download
                                    >
                                        {{ t('order.download', { name: f.name }) }} <span class="font-normal text-ink-muted">{{ formatFileSize(f.size) }}</span>
                                    </a>
                                </li>
                            </ul>
                            <p v-else-if="i.is_digital && !order.is_paid" class="mt-1 mb-0 text-sm text-ink-muted">{{ t('order.fileAfterPayment') }}</p>
                        </div>
                        <div class="font-bold text-plum">{{ formatPrice(i.total.amount_minor, i.total.currency) }}</div>
                    </li>
                </ul>

                <div v-if="g.shipment || g.cancel_reason" class="grid gap-2 border-t border-line bg-lavender-50/50 px-5 py-4 text-[15px]">
                    <p v-if="g.cancel_reason" class="m-0 text-kalyna">{{ t('order.makerCancelled', { reason: g.cancel_reason }) }}</p>
                    <template v-if="g.shipment">
                        <p class="m-0">
                            <span class="text-ink-muted">{{ t('order.deliveryTo') }}</span>
                            {{ g.shipment.recipient_name }}, {{ g.shipment.phone }} — {{ countryName(g.shipment.country_code) }}, {{ g.shipment.city }},
                            {{ g.shipment.address }}<template v-if="g.shipment.postal_code">, {{ g.shipment.postal_code }}</template>
                        </p>
                        <p v-if="g.shipment.tracking_number" class="m-0">
                            <span class="text-ink-muted">{{ t('order.tracking') }}</span><strong>{{ g.shipment.tracking_number }}</strong>
                        </p>
                    </template>
                </div>
                <div class="flex justify-end gap-6 border-t border-line px-5 py-3 text-[15px]">
                    <span v-if="g.shipment" class="text-ink-muted">{{ t('order.deliveryAmount', { amount: formatPrice(g.delivery_fee.amount_minor, g.delivery_fee.currency) }) }}</span>
                    <span class="font-semibold">{{ t('order.totalAmount', { amount: formatPrice(g.total.amount_minor, g.total.currency) }) }}</span>
                </div>
            </section>

            <div class="mt-6 flex justify-end">
                <div class="w-full max-w-[320px] rounded-card bg-lavender-50 p-5">
                    <div class="flex justify-between text-[15px]"><span class="text-ink-muted">{{ t('checkout.items') }}</span><span>{{ formatPrice(order.subtotal.amount_minor, order.subtotal.currency) }}</span></div>
                    <div class="mt-1 flex justify-between text-[15px]"><span class="text-ink-muted">{{ t('checkout.delivery') }}</span><span>{{ formatPrice(order.delivery.amount_minor, order.delivery.currency) }}</span></div>
                    <div class="mt-3 flex items-baseline justify-between border-t border-lavender-200 pt-3">
                        <span class="font-semibold">{{ t('checkout.total') }}</span>
                        <span class="text-2xl font-bold text-plum">{{ formatPrice(order.total.amount_minor, order.total.currency) }}</span>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
