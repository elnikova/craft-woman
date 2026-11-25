<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseInput from '@/components/ui/BaseInput.vue';
import BaseTextarea from '@/components/ui/BaseTextarea.vue';
import StatusPill from '@/components/orders/StatusPill.vue';
import { ordersApi } from '@/api/orders';
import { toApiError } from '@/api/http';
import type { GroupStatus, Sale } from '@/types/order';
import { formatPrice } from '@/utils/format';
import { COUNTRIES, GROUP_STATUS_LABELS, formatDate } from '@/utils/orderStatus';

const TABS: (GroupStatus | 'all')[] = ['awaiting_shipment', 'shipped', 'pending_payment', 'delivered', 'cancelled', 'all'];
const tab = ref<GroupStatus | 'all'>('awaiting_shipment');
const sales = ref<Sale[]>([]);
const loading = ref(true);
const error = ref('');

/** Which card has an action open, and its form data */
const open = reactive<{ uuid: string | null; mode: 'ship' | 'cancel' | null; tracking: string; reason: string; error: string; busy: boolean }>({
    uuid: null,
    mode: null,
    tracking: '',
    reason: '',
    error: '',
    busy: false,
});

const countryName = (code: string) => COUNTRIES.find((c) => c.code === code)?.name ?? code;

async function load() {
    loading.value = true;
    error.value = '';
    try {
        sales.value = (await ordersApi.sales(tab.value === 'all' ? undefined : tab.value)).data;
    } catch (e) {
        error.value = toApiError(e).message;
    } finally {
        loading.value = false;
    }
}

function start(sale: Sale, mode: 'ship' | 'cancel') {
    Object.assign(open, { uuid: sale.uuid, mode, tracking: '', reason: '', error: '', busy: false });
}

async function run(sale: Sale, action: () => Promise<Sale>) {
    open.busy = true;
    open.error = '';
    try {
        const updated = await action();
        sales.value = sales.value.map((s) => (s.uuid === updated.uuid ? updated : s));
        open.uuid = null;
        open.mode = null;
    } catch (e) {
        const err = toApiError(e);
        open.error = Object.values(err.fields)[0] ?? err.message;
    } finally {
        open.busy = false;
    }
}

const ship = (s: Sale) => run(s, () => ordersApi.ship(s.uuid, open.tracking.trim() || null));
const cancel = (s: Sale) => run(s, () => ordersApi.cancelSale(s.uuid, open.reason.trim()));
function deliver(s: Sale) {
    open.uuid = s.uuid;
    run(s, () => ordersApi.deliver(s.uuid));
}

watch(tab, load);
onMounted(load);
</script>

<template>
    <div class="max-w-[1080px]">
        <h1 class="font-flair m-0 text-[40px] leading-tight font-bold tracking-[-0.015em] text-plum">Продажі</h1>
        <p class="mt-2 mb-0 text-ink-muted">Тут ваша частина кожного замовлення: що відправити і куди.</p>

        <div role="tablist" aria-label="Статус" class="mt-8 flex flex-wrap gap-2">
            <button
                v-for="t in TABS"
                :key="t"
                type="button"
                role="tab"
                :aria-selected="tab === t"
                class="h-11 rounded-full border px-[18px] text-[15px]"
                :class="tab === t ? 'border-plum bg-plum text-white' : 'border-line-strong bg-white text-ink hover:border-plum'"
                @click="tab = t"
            >
                {{ t === 'all' ? 'Усі' : t === 'awaiting_shipment' ? 'Треба відправити' : GROUP_STATUS_LABELS[t] }}
            </button>
        </div>

        <div v-if="loading" class="mt-8 space-y-3" aria-busy="true" aria-label="Завантаження">
            <div v-for="i in 3" :key="i" class="h-40 animate-pulse rounded-card bg-lavender-50" />
        </div>

        <div v-else-if="error" role="alert" class="mt-8 flex flex-col items-start gap-4 rounded-card border border-kalyna/30 bg-kalyna/5 p-6">
            <p class="m-0 text-kalyna">{{ error }}</p>
            <BaseButton variant="secondary" @click="load">Спробувати ще раз</BaseButton>
        </div>

        <p v-else-if="sales.length === 0" class="mt-10 text-lg text-ink-muted">
            {{ tab === 'awaiting_shipment' ? 'Нічого не чекає на відправку.' : 'Тут поки порожньо.' }}
        </p>

        <ul v-else class="mt-8 mb-0 flex list-none flex-col gap-4 p-0">
            <li v-for="s in sales" :key="s.uuid" class="rounded-card border border-line bg-white">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3">
                    <div>
                        <span class="font-semibold text-plum">№ {{ s.order_number }}</span>
                        <span class="ml-2 text-sm text-ink-muted">{{ formatDate(s.placed_at) }}</span>
                    </div>
                    <StatusPill :status="s.status" kind="group" />
                </header>

                <div class="grid gap-5 px-5 py-4 md:grid-cols-[1fr_300px]">
                    <ul class="m-0 flex list-none flex-col gap-2 p-0 text-[15px]">
                        <li v-for="i in s.items" :key="i.uuid" class="flex justify-between gap-4">
                            <span>
                                <span class="font-semibold">{{ i.name }}</span>
                                <span class="text-ink-muted"> {{ i.is_digital ? '· файл' : i.variant_name ? `· ${i.variant_name}` : '' }} × {{ i.quantity }}</span>
                            </span>
                            <span>{{ formatPrice(i.total.amount_minor, i.total.currency) }}</span>
                        </li>
                        <li class="flex justify-between gap-4 border-t border-line pt-2">
                            <span class="text-ink-muted">
                                {{ s.payment_method === 'cash_on_delivery' ? (s.is_paid ? 'Оплачено при отриманні' : 'Оплата при отриманні') : s.is_paid ? 'Оплачено онлайн' : 'Чекає онлайн-оплати' }}
                            </span>
                            <strong>{{ formatPrice(s.total.amount_minor, s.total.currency) }}</strong>
                        </li>
                    </ul>

                    <div v-if="s.shipment" class="rounded-[12px] bg-lavender-50 p-4 text-[15px]">
                        <div class="font-semibold">{{ s.shipment.recipient_name }}</div>
                        <div>{{ s.shipment.phone }}</div>
                        <div class="mt-1 text-ink-muted">
                            {{ countryName(s.shipment.country_code) }}, {{ s.shipment.city }}<br />{{
                                [s.shipment.address, s.shipment.postal_code].filter(Boolean).join(', ')
                            }}
                        </div>
                        <div v-if="s.shipment.tracking_number" class="mt-2">ТТН: <strong>{{ s.shipment.tracking_number }}</strong></div>
                    </div>
                    <div v-else class="rounded-[12px] bg-lavender-50 p-4 text-[15px] text-ink-muted">Цифрове замовлення — відправляти нічого не треба.</div>
                </div>

                <p v-if="s.cancel_reason" class="m-0 px-5 pb-4 text-kalyna">Причина скасування: {{ s.cancel_reason }}</p>

                <!-- Actions -->
                <div v-if="s.status === 'awaiting_shipment' || s.status === 'shipped'" class="border-t border-line px-5 py-4">
                    <form v-if="open.uuid === s.uuid && open.mode === 'ship'" class="flex flex-wrap items-end gap-3" @submit.prevent="ship(s)">
                        <BaseInput v-model="open.tracking" label="Номер відправлення (ТТН)" hint="Необов’язково, але покупцю так спокійніше." class="min-w-[260px] flex-1" />
                        <BaseButton type="submit" :loading="open.busy">Позначити відправленим</BaseButton>
                        <BaseButton variant="ghost" @click="open.uuid = null">Скасувати</BaseButton>
                    </form>
                    <form v-else-if="open.uuid === s.uuid && open.mode === 'cancel'" class="flex flex-col gap-3" @submit.prevent="cancel(s)">
                        <BaseTextarea v-model="open.reason" label="Чому скасовуєте" hint="Покупець побачить це пояснення." :rows="3" />
                        <div class="flex flex-wrap gap-3">
                            <BaseButton type="submit" :loading="open.busy">Скасувати мою частину</BaseButton>
                            <BaseButton variant="ghost" @click="open.uuid = null">Назад</BaseButton>
                        </div>
                    </form>
                    <div v-else class="flex flex-wrap gap-3">
                        <template v-if="s.status === 'awaiting_shipment'">
                            <BaseButton @click="start(s, 'ship')">Відправлено</BaseButton>
                            <BaseButton variant="ghost" @click="start(s, 'cancel')">Не можу виконати</BaseButton>
                        </template>
                        <BaseButton v-else variant="secondary" :loading="open.busy && open.uuid === s.uuid" @click="deliver(s)">Покупець отримав</BaseButton>
                    </div>
                    <p v-if="open.uuid === s.uuid && open.error" role="alert" class="mt-3 mb-0 text-kalyna">{{ open.error }}</p>
                </div>
            </li>
        </ul>
    </div>
</template>
