<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import ProductCover from '@/components/products/ProductCover.vue';
import ProductCard from '@/components/products/ProductCard.vue';
import ProductStatusBadge from '@/components/products/ProductStatusBadge.vue';
import MakerAvatar from '@/components/makers/MakerAvatar.vue';
import { productsApi } from '@/api/products';
import { toApiError } from '@/api/http';
import type { Product } from '@/types/product';
import { formatPrice, plural } from '@/utils/format';

const props = defineProps<{ slug: string }>();

const product = ref<Product | null>(null);
const more = ref<Product[]>([]);
const loading = ref(true);
const notFound = ref(false);
const error = ref('');
const imageIndex = ref(0);
const variantUuid = ref<string | null>(null);

const variant = computed(() => product.value?.variants.find((v) => v.uuid === variantUuid.value) ?? null);
const price = computed(() => variant.value?.price ?? product.value?.price ?? null);
const paragraphs = computed(() => (product.value?.description ?? '').split(/\n{2,}/).filter(Boolean));
const stockNote = computed(() => {
    if (!product.value || product.value.is_digital) return 'Цифровий файл — доступ одразу після оплати';
    if (!variant.value) return '';
    if (!variant.value.in_stock) return 'Зараз немає в наявності';
    if (variant.value.stock !== null) return `Лишилось ${plural(variant.value.stock, ['штука', 'штуки', 'штук'])}`;
    return 'Є в наявності';
});

async function load() {
    loading.value = true;
    notFound.value = false;
    error.value = '';
    imageIndex.value = 0;
    try {
        const p = await productsApi.show(props.slug);
        product.value = p;
        variantUuid.value = (p.variants.find((v) => v.in_stock) ?? p.variants[0])?.uuid ?? null;
        document.title = `${p.name} — Craft Woman`;
        if (p.maker) {
            more.value = (await productsApi.list({ maker: p.maker.slug, per_page: 5 })).data.filter((x) => x.uuid !== p.uuid).slice(0, 4);
        }
    } catch (e) {
        const err = toApiError(e);
        if (err.status === 404) notFound.value = true;
        else error.value = err.message;
    } finally {
        loading.value = false;
    }
}

watch(() => props.slug, load, { immediate: true });
</script>

<template>
    <div v-if="loading" class="mx-auto grid max-w-[1280px] animate-pulse gap-10 px-6 py-12 md:grid-cols-2 md:px-8" aria-busy="true" aria-label="Завантаження">
        <div class="aspect-square rounded-card bg-lavender-50" />
        <div class="space-y-4"><div class="h-10 w-3/4 rounded bg-lavender-50" /><div class="h-8 w-1/3 rounded bg-lavender-50" /></div>
    </div>

    <div v-else-if="notFound" class="mx-auto flex max-w-[1280px] flex-col items-start gap-4 px-6 py-20 md:px-8">
        <h1 class="font-flair m-0 text-4xl font-bold text-plum">Такого товару немає</h1>
        <p class="m-0 text-lg text-ink-muted">Можливо, його зняли з продажу або посилання змінилося.</p>
        <BaseButton :to="{ name: 'catalog' }">До каталогу</BaseButton>
    </div>

    <div v-else-if="error" role="alert" class="mx-auto flex max-w-[1280px] flex-col items-start gap-4 px-6 py-20 md:px-8">
        <p class="m-0 text-kalyna">{{ error }}</p>
        <BaseButton variant="secondary" @click="load">Спробувати ще раз</BaseButton>
    </div>

    <template v-else-if="product">
        <div v-if="product.status && product.status !== 'published'" class="bg-notice-bg">
            <div class="mx-auto flex max-w-[1280px] flex-wrap items-center gap-3 px-6 py-3 text-notice-ink md:px-8">
                <ProductStatusBadge :status="product.status" />
                <span>Товар не опубліковано: так його бачите тільки ви.</span>
            </div>
        </div>

        <div class="mx-auto max-w-[1280px] px-6 py-10 md:px-8">
            <nav aria-label="Шлях" class="mb-6 text-[15px] text-ink-muted">
                <RouterLink :to="{ name: 'catalog' }" class="text-plum">Каталог</RouterLink>
                <template v-if="product.category">
                    / <RouterLink :to="{ name: 'catalog', query: { category: product.category.slug } }" class="text-plum">{{ product.category.name }}</RouterLink>
                </template>
            </nav>

            <div class="grid gap-12 md:grid-cols-2">
                <!-- Галерея -->
                <div class="flex flex-col gap-3">
                    <ProductCover :uuid="product.uuid" :name="product.name" :src="product.images[imageIndex]?.url" />
                    <div v-if="product.images.length > 1" class="flex flex-wrap gap-2" role="group" aria-label="Фото товару">
                        <button
                            v-for="(img, i) in product.images"
                            :key="img.uuid"
                            type="button"
                            class="h-20 w-20 overflow-hidden rounded-[12px] border-2"
                            :class="i === imageIndex ? 'border-plum' : 'border-transparent'"
                            :aria-label="`Фото ${i + 1}`"
                            :aria-pressed="i === imageIndex"
                            @click="imageIndex = i"
                        >
                            <img :src="img.url" alt="" class="h-full w-full object-cover" />
                        </button>
                    </div>
                </div>

                <!-- Покупка -->
                <div class="flex flex-col gap-5">
                    <h1 class="font-flair m-0 text-[36px] leading-tight font-bold tracking-[-0.015em] text-plum md:text-[44px]">{{ product.name }}</h1>
                    <div v-if="price" class="text-3xl font-bold text-plum">{{ formatPrice(price.amount_minor, price.currency) }}</div>

                    <fieldset v-if="product.variants.length > 1" class="m-0 border-0 p-0">
                        <legend class="mb-2 text-[15px] font-semibold text-ink">Варіант</legend>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="v in product.variants"
                                :key="v.uuid"
                                type="button"
                                :aria-pressed="variantUuid === v.uuid"
                                class="h-11 rounded-full border-2 px-4 text-[15px]"
                                :class="[
                                    variantUuid === v.uuid ? 'border-plum bg-lavender-50 font-semibold text-plum' : 'border-line-strong bg-white text-ink',
                                    !v.in_stock ? 'line-through opacity-60' : '',
                                ]"
                                @click="variantUuid = v.uuid"
                            >
                                {{ v.name }}
                            </button>
                        </div>
                    </fieldset>

                    <p class="m-0 text-[15px]" :class="variant && !variant.in_stock ? 'text-kalyna' : 'text-ink-muted'">{{ stockNote }}</p>

                    <div class="flex flex-wrap gap-3">
                        <BaseButton size="lg" disabled title="Кошик з’явиться наступним кроком">Додати в кошик</BaseButton>
                        <span class="self-center text-sm text-ink-muted">Кошик — скоро</span>
                    </div>

                    <RouterLink
                        v-if="product.maker"
                        :to="{ name: 'maker', params: { slug: product.maker.slug } }"
                        class="mt-2 flex items-center gap-4 rounded-card bg-lavender-50 p-4 text-inherit no-underline hover:bg-lavender-100"
                    >
                        <MakerAvatar :uuid="product.maker.uuid" :name="product.maker.display_name" :src="product.maker.avatar_url" shape="circle" class="w-14 shrink-0" />
                        <div>
                            <div class="text-sm text-ink-muted">Майстер</div>
                            <div class="font-semibold text-plum">{{ product.maker.display_name }}</div>
                            <div v-if="product.maker.city" class="text-sm text-ink-muted">{{ product.maker.city }}</div>
                        </div>
                    </RouterLink>

                    <dl v-if="!product.is_digital" class="m-0 grid gap-3 border-t border-line pt-5 text-[15px]">
                        <div v-if="product.production_time_days" class="flex gap-2">
                            <dt class="font-semibold text-ink">Виготовлення:</dt>
                            <dd class="m-0 text-ink-muted">до {{ plural(product.production_time_days, ['дня', 'днів', 'днів']) }}</dd>
                        </div>
                        <div v-if="product.shipping_info" class="flex gap-2">
                            <dt class="font-semibold text-ink">Доставка:</dt>
                            <dd class="m-0 text-ink-muted">{{ product.shipping_info }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <section class="mt-16 grid gap-10 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]" aria-labelledby="about-title">
                <div>
                    <h2 id="about-title" class="font-flair m-0 text-3xl font-bold text-plum">Про товар</h2>
                    <div class="mt-5 flex max-w-[68ch] flex-col gap-4 text-[17px] leading-relaxed text-ink">
                        <p v-for="(p, i) in paragraphs" :key="i" class="m-0">{{ p }}</p>
                    </div>
                </div>
                <div v-if="product.tags.length" class="flex flex-wrap content-start gap-2">
                    <RouterLink
                        v-for="t in product.tags"
                        :key="t"
                        :to="{ name: 'catalog', query: { q: t } }"
                        class="rounded-full border border-line-strong px-3 py-1.5 text-sm text-plum no-underline hover:border-plum"
                    >
                        #{{ t }}
                    </RouterLink>
                </div>
            </section>

            <section v-if="more.length" class="mt-16" aria-labelledby="more-title">
                <h2 id="more-title" class="font-flair m-0 text-3xl font-bold text-plum">Ще від {{ product.maker?.display_name }}</h2>
                <div class="mt-8 grid grid-cols-[repeat(auto-fill,minmax(220px,1fr))] gap-6">
                    <ProductCard v-for="p in more" :key="p.uuid" :product="p" />
                </div>
            </section>
        </div>
    </template>
</template>
