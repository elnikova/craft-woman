<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseInput from '@/components/ui/BaseInput.vue';
import { checkoutApi } from '@/api/orders';
import { toApiError, type FieldErrors } from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import type { DeliveryAddress, PaymentMethod, Quote } from '@/types/order';
import { formatPrice, plural } from '@/utils/format';
import { countries, PAYMENT_LABELS } from '@/utils/orderStatus';

const router = useRouter();
const auth = useAuthStore();
const cart = useCartStore();
const { t } = useI18n();

const address = reactive<DeliveryAddress>({
    recipient_name: auth.user?.name ?? '',
    phone: '',
    country_code: 'UA',
    city: '',
    address: '',
    postal_code: '',
});
const payment = ref<PaymentMethod>('online');

const quote = ref<Quote | null>(null);
const quoting = ref(true);
const quoteError = ref('');
const placing = ref(false);
const errors = ref<FieldErrors>({});
const formError = ref('');

const methods = computed(() => quote.value?.payment_methods ?? []);

async function loadQuote() {
    quoting.value = true;
    quoteError.value = '';
    try {
        quote.value = await checkoutApi.quote(address.country_code);
        // If the chosen method became unavailable (e.g. the country changed), fall back to online payment
        if (!methods.value.find((m) => m.method === payment.value)?.available) payment.value = 'online';
    } catch (e) {
        // Keep the previous quote on screen but block checkout until the error clears
        quoteError.value = toApiError(e).message;
    } finally {
        quoting.value = false;
    }
}

async function place() {
    if (!quote.value) return;
    placing.value = true;
    errors.value = {};
    formError.value = '';
    try {
        const order = await checkoutApi.place({
            payment_method: payment.value,
            delivery: quote.value.needs_address ? { ...address, postal_code: address.postal_code.trim() } : null,
            expected_total_minor: quote.value.total.amount_minor,
        });
        cart.reset();
        await router.push({ name: 'account-order', params: { number: order.order_number }, query: { placed: '1' } });
    } catch (e) {
        const err = toApiError(e);
        errors.value = Object.fromEntries(Object.entries(err.fields).map(([k, v]) => [k.replace('delivery.', ''), v]));
        formError.value = err.status === 422 ? t('checkout.checkAddress') : err.message;
        if (err.status === 409) await loadQuote();
    } finally {
        placing.value = false;
    }
}

watch(() => address.country_code, loadQuote);
onMounted(loadQuote);
</script>

<template>
    <div class="mx-auto max-w-[1080px] px-5 py-10 md:px-8">
        <RouterLink :to="{ name: 'cart' }" class="text-[15px] text-plum">{{ t('checkout.backToCart') }}</RouterLink>
        <h1 class="font-flair mt-3 mb-0 text-[40px] leading-tight font-bold tracking-[-0.015em] text-plum">{{ t('checkout.title') }}</h1>

        <div v-if="quoting && !quote" class="mt-8 h-64 animate-pulse rounded-card bg-lavender-50" aria-busy="true" :aria-label="t('common.loading')" />

        <div v-else-if="!quote" role="alert" class="mt-8 flex flex-col items-start gap-4 rounded-card border border-kalyna/30 bg-kalyna/5 p-6">
            <p class="m-0 text-kalyna">{{ quoteError }}</p>
            <div class="flex flex-wrap gap-3">
                <BaseButton variant="secondary" :to="{ name: 'cart' }">{{ t('checkout.returnToCart') }}</BaseButton>
                <select
                    v-model="address.country_code"
                    :aria-label="t('checkout.countryLabel')"
                    class="h-11 rounded-full border border-line-strong bg-white px-4 text-[15px] text-ink"
                >
                    <option v-for="c in countries()" :key="c.code" :value="c.code">{{ c.name }}</option>
                </select>
            </div>
        </div>

        <form v-else class="mt-8 grid items-start gap-8 lg:grid-cols-[1fr_360px]" novalidate @submit.prevent="place">
            <div class="flex flex-col gap-8">
                <fieldset v-if="quote.needs_address" class="m-0 flex flex-col gap-5 border-0 p-0">
                    <legend class="mb-4 text-xl font-bold text-plum">{{ t('checkout.delivery') }}</legend>
                    <div class="flex flex-col gap-2">
                        <label for="country" class="text-[15px] font-semibold text-ink">{{ t('checkout.country') }}</label>
                        <select
                            id="country"
                            v-model="address.country_code"
                            autocomplete="country"
                            class="h-[52px] rounded-control border-[1.5px] border-line-strong bg-white px-4 text-base text-ink focus:border-plum focus:outline-none"
                        >
                            <option v-for="c in countries()" :key="c.code" :value="c.code">{{ c.name }}</option>
                        </select>
                        <p v-if="quoteError" role="alert" class="m-0 text-sm text-kalyna">{{ quoteError }}</p>
                    </div>
                    <div class="grid gap-5 md:grid-cols-2">
                        <BaseInput v-model="address.recipient_name" :label="t('checkout.recipient')" autocomplete="name" required :error="errors.recipient_name" />
                        <BaseInput v-model="address.phone" :label="t('checkout.phone')" autocomplete="tel" placeholder="+380…" required :error="errors.phone" />
                    </div>
                    <div class="grid gap-5 md:grid-cols-[1fr_160px]">
                        <BaseInput v-model="address.city" :label="t('checkout.city')" autocomplete="address-level2" required :error="errors.city" />
                        <BaseInput v-model="address.postal_code" :label="t('checkout.postalCode')" autocomplete="postal-code" :error="errors.postal_code" />
                    </div>
                    <BaseInput
                        v-model="address.address"
                        :label="t('checkout.address')"
                        autocomplete="street-address"
                        required
                        :error="errors.address"
                    />
                </fieldset>
                <p v-else class="m-0 rounded-card bg-lavender-50 p-5 text-ink">
                    {{ t('checkout.digitalOnly') }}
                </p>

                <fieldset class="m-0 flex flex-col gap-3 border-0 p-0">
                    <legend class="mb-4 text-xl font-bold text-plum">{{ t('checkout.payment') }}</legend>
                    <label
                        v-for="m in methods"
                        :key="m.method"
                        class="flex items-start gap-3 rounded-card border-[1.5px] p-4"
                        :class="[
                            payment === m.method ? 'border-plum bg-lavender-50' : 'border-line-strong bg-white',
                            m.available ? 'cursor-pointer' : 'opacity-70',
                        ]"
                    >
                        <input v-model="payment" type="radio" name="payment" :value="m.method" :disabled="!m.available" class="mt-1 h-5 w-5 accent-plum" />
                        <span>
                            <span class="block font-semibold text-ink">{{ PAYMENT_LABELS[m.method] }}</span>
                            <span v-if="m.method === 'online'" class="block text-sm text-ink-muted">{{ t('checkout.onlineHint') }}</span>
                            <span v-else-if="m.available" class="block text-sm text-ink-muted">{{ t('checkout.codHint') }}</span>
                            <span v-else class="block text-sm text-ink-muted">{{ t('checkout.unavailable', { reasons: m.reasons.join('; ') }) }}</span>
                        </span>
                    </label>
                </fieldset>
            </div>

            <aside class="flex flex-col gap-4 rounded-card bg-lavender-50 p-6 lg:sticky lg:top-6" :aria-busy="quoting">
                <h2 class="m-0 text-lg font-bold text-plum">{{ t('checkout.yourOrder') }}</h2>
                <ul class="m-0 flex list-none flex-col gap-3 p-0">
                    <li v-for="g in quote.groups" :key="g.maker?.uuid" class="border-b border-lavender-200 pb-3 text-[15px]">
                        <div class="flex justify-between gap-3">
                            <span class="font-semibold text-ink">{{ g.maker?.display_name }}</span>
                            <span>{{ formatPrice(g.subtotal.amount_minor, g.subtotal.currency) }}</span>
                        </div>
                        <div class="flex justify-between gap-3 text-ink-muted">
                            <span>{{ plural(g.items_count, 'items') }}</span>
                            <span v-if="g.has_physical">
                                {{ t('checkout.deliveryFee', { fee: g.delivery_fee.amount_minor ? formatPrice(g.delivery_fee.amount_minor, g.delivery_fee.currency) : t('common.free') }) }}
                            </span>
                        </div>
                    </li>
                </ul>
                <div class="flex justify-between text-[15px]">
                    <span class="text-ink-muted">{{ t('checkout.items') }}</span><span>{{ formatPrice(quote.subtotal.amount_minor, quote.currency) }}</span>
                </div>
                <div v-if="quote.needs_address" class="flex justify-between text-[15px]">
                    <span class="text-ink-muted">{{ t('checkout.delivery') }}</span><span>{{ formatPrice(quote.delivery.amount_minor, quote.currency) }}</span>
                </div>
                <div class="flex items-baseline justify-between border-t border-lavender-200 pt-3">
                    <span class="font-semibold">{{ t('checkout.total') }}</span>
                    <span class="text-2xl font-bold text-plum">{{ formatPrice(quote.total.amount_minor, quote.currency) }}</span>
                </div>
                <p v-if="quote.groups.length > 1" class="m-0 text-sm text-ink-muted">
                    {{ t('checkout.splitNote') }}
                </p>
                <div v-if="formError" role="alert" class="rounded-[10px] border border-kalyna/30 bg-white px-3.5 py-3 text-sm text-kalyna">{{ formError }}</div>
                <BaseButton type="submit" size="lg" block :loading="placing" :disabled="quoting || !!quoteError">
                    {{ payment === 'online' ? t('checkout.toPayment') : t('checkout.confirm') }}
                </BaseButton>
            </aside>
        </form>
    </div>
</template>
