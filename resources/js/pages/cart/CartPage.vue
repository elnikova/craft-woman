<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { labelMap, localePath } from '@/i18n';
import { dayLabel } from '@/utils/workshop';
import BaseButton from '@/components/ui/BaseButton.vue';
import ProductCover from '@/components/products/ProductCover.vue';
import { toApiError } from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import type { CartIssue, CartItem } from '@/types/order';
import { formatPrice, plural } from '@/utils/format';

const auth = useAuthStore();
const { t } = useI18n();
const store = useCartStore();
const cart = computed(() => store.cart);
const busy = ref<string | null>(null);
const error = ref('');

const ISSUES = labelMap<CartIssue>('cart.issue', ['unavailable', 'sold_out', 'insufficient_stock']);

const checkoutTo = computed(() =>
    auth.isAuthenticated ? { name: 'checkout' } : { name: 'login', query: { redirect: localePath('/checkout') } },
);

async function run(item: string, action: () => Promise<unknown>) {
    busy.value = item;
    error.value = '';
    try {
        await action();
    } catch (e) {
        error.value = toApiError(e).message;
    } finally {
        busy.value = null;
    }
}

const change = (i: CartItem, delta: number) => run(i.uuid, () => store.setQuantity(i.uuid, i.quantity + delta));
const remove = (i: CartItem) => run(i.uuid, () => store.remove(i.uuid));

onMounted(() => store.load().catch(() => undefined));
</script>

<template>
    <div class="mx-auto max-w-[1080px] px-5 py-10 md:px-8">
        <h1 class="font-flair m-0 text-[40px] leading-tight font-bold tracking-[-0.015em] text-plum">{{ t('cart.title') }}</h1>

        <div v-if="!store.loaded" class="mt-8 space-y-3" aria-busy="true" :aria-label="t('common.loading')">
            <div v-for="i in 3" :key="i" class="h-28 animate-pulse rounded-card bg-lavender-50" />
        </div>

        <div v-else-if="store.isEmpty" class="mt-10 flex flex-col items-start gap-4">
            <p class="m-0 text-lg text-ink-muted">{{ t('cart.empty') }}</p>
            <BaseButton :to="{ name: 'catalog' }">{{ t('common.toCatalog') }}</BaseButton>
        </div>

        <div v-else class="mt-8 grid items-start gap-8 lg:grid-cols-[1fr_340px]">
            <div class="flex flex-col gap-6">
                <div v-if="error" role="alert" class="rounded-[10px] border border-kalyna/30 bg-kalyna/5 px-4 py-3 text-kalyna">{{ error }}</div>

                <section
                    v-for="group in cart.groups"
                    :key="group.maker?.uuid ?? 'unknown'"
                    class="rounded-card border border-line bg-white"
                    :aria-label="t('cart.fromMaker', { name: group.maker?.display_name ?? '' })"
                >
                    <header class="flex items-baseline justify-between gap-4 border-b border-line px-5 py-3">
                        <RouterLink
                            v-if="group.maker"
                            :to="{ name: 'maker', params: { slug: group.maker.slug } }"
                            class="font-semibold text-plum no-underline hover:underline"
                        >
                            {{ group.maker.display_name }}
                        </RouterLink>
                        <span v-if="group.maker?.city" class="text-sm text-ink-muted">{{ group.maker.city }}</span>
                    </header>

                    <ul class="m-0 list-none p-0">
                        <li
                            v-for="item in group.items"
                            :key="item.uuid"
                            class="flex flex-wrap items-center gap-4 border-b border-line px-5 py-4 last:border-b-0"
                            :class="item.issue ? 'bg-kalyna/5' : ''"
                        >
                            <RouterLink v-if="item.slug" :to="{ name: item.kind, params: { slug: item.slug } }" class="shrink-0" tabindex="-1">
                                <ProductCover :uuid="item.product_uuid" :name="item.name" :src="item.image_url" class="!w-20" />
                            </RouterLink>
                            <div class="min-w-0 flex-[1_1_200px]">
                                <RouterLink
                                    v-if="item.slug"
                                    :to="{ name: item.kind, params: { slug: item.slug } }"
                                    class="font-semibold text-ink no-underline hover:text-plum"
                                    >{{ item.name }}</RouterLink
                                >
                                <span v-else class="font-semibold">{{ item.name }}</span>
                                <div class="mt-0.5 text-sm text-ink-muted">
                                    {{ item.kind === 'workshop' ? t('cartWorkshop.line', { date: item.starts_at ? dayLabel(item.starts_at) : '' }) : item.kind === 'course' ? t('courses.onlineCourse') : item.is_digital ? t('common.digitalFile') : item.variant_name }}
                                </div>
                                <p v-if="item.issue" class="mt-1 mb-0 text-sm font-semibold text-kalyna">{{ ISSUES[item.issue] }}</p>
                                <p v-else-if="item.price_changed" class="mt-1 mb-0 text-sm text-warning-ink">{{ t('cart.priceChanged') }}</p>
                            </div>

                            <div v-if="!item.is_single_unit" class="flex h-10 items-center rounded-full border border-line-strong" role="group" :aria-label="t('common.quantityOf', { name: item.name })">
                                <button
                                    type="button"
                                    class="h-full w-10 text-lg text-plum disabled:opacity-40"
                                    :aria-label="t('common.less')"
                                    :disabled="item.quantity <= 1 || busy === item.uuid"
                                    @click="change(item, -1)"
                                >
                                    −
                                </button>
                                <span class="w-7 text-center font-semibold">{{ item.quantity }}</span>
                                <button
                                    type="button"
                                    class="h-full w-10 text-lg text-plum disabled:opacity-40"
                                    :aria-label="t('common.more')"
                                    :disabled="busy === item.uuid || (item.available_stock !== null && item.quantity >= item.available_stock)"
                                    @click="change(item, 1)"
                                >
                                    +
                                </button>
                            </div>

                            <div class="w-24 text-right font-bold text-plum">{{ formatPrice(item.total.amount_minor, item.total.currency) }}</div>
                            <button
                                type="button"
                                class="h-10 rounded-full px-3 text-sm text-ink-muted hover:bg-lavender-50 hover:text-plum"
                                :disabled="busy === item.uuid"
                                @click="remove(item)"
                            >{{ t('cart.remove') }}</button>
                        </li>
                    </ul>
                </section>
            </div>

            <aside class="flex flex-col gap-4 rounded-card bg-lavender-50 p-6 lg:sticky lg:top-6">
                <div class="flex items-baseline justify-between">
                    <span class="text-ink-muted">{{ plural(cart.items_count, 'items') }}</span>
                    <span class="text-2xl font-bold text-plum">{{ formatPrice(cart.subtotal_minor, cart.currency ?? 'EUR') }}</span>
                </div>
                <p class="m-0 text-sm text-ink-muted">{{ t('cart.deliveryNote') }}</p>
                <p v-if="cart.has_issues" class="m-0 text-sm font-semibold text-kalyna">{{ t('cart.fixIssues') }}</p>
                <BaseButton size="lg" block :to="cart.has_issues ? undefined : checkoutTo" :disabled="cart.has_issues">{{ t('cart.checkout') }}</BaseButton>
                <p v-if="!auth.isAuthenticated" class="m-0 text-sm text-ink-muted">{{ t('cart.loginNote') }}</p>
            </aside>
        </div>
    </div>
</template>
