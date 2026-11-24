<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router';
import BaseButton from '@/components/ui/BaseButton.vue';
import ProductCard from '@/components/products/ProductCard.vue';
import OrnamentBand from '@/components/ornament/OrnamentBand.vue';
import { productsApi } from '@/api/products';
import { taxonomyApi } from '@/api/taxonomy';
import { toApiError } from '@/api/http';
import type { Product, ProductFilters, ProductType } from '@/types/product';
import type { Category } from '@/types/taxonomy';
import { plural } from '@/utils/format';

const route = useRoute();
const router = useRouter();

const categories = ref<Category[]>([]);
const products = ref<Product[]>([]);
const total = ref(0);
const page = ref(1);
const lastPage = ref(1);
const loading = ref(true);
const loadingMore = ref(false);
const error = ref('');
const search = ref('');

/** Фільтри живуть в URL: посилання можна скопіювати, «Назад» працює */
const filters = computed<ProductFilters>(() => ({
    category: typeof route.query.category === 'string' ? route.query.category : undefined,
    type: route.query.type === 'physical' || route.query.type === 'digital' ? (route.query.type as ProductType) : undefined,
    sort: route.query.sort === 'price_asc' || route.query.sort === 'price_desc' ? route.query.sort : 'newest',
    q: typeof route.query.q === 'string' ? route.query.q : undefined,
}));

const activeCategory = computed(() => {
    for (const c of categories.value) {
        if (c.slug === filters.value.category) return c;
        const child = c.children?.find((ch) => ch.slug === filters.value.category);
        if (child) return child;
    }
    return null;
});
const hasFilters = computed(() => !!(filters.value.category || filters.value.type || filters.value.q));

function setFilter(patch: Partial<Record<keyof ProductFilters, string | undefined>>) {
    const query: LocationQueryRaw = { ...route.query, ...patch };
    for (const k of Object.keys(query)) if (query[k] === undefined || query[k] === '' || (k === 'sort' && query[k] === 'newest')) delete query[k];
    void router.replace({ query });
}

async function load(reset = true) {
    error.value = '';
    if (reset) {
        loading.value = true;
        page.value = 1;
    } else loadingMore.value = true;
    try {
        const res = await productsApi.list({ ...filters.value, page: page.value, per_page: 12 });
        products.value = reset ? res.data : [...products.value, ...res.data];
        total.value = res.meta.total;
        lastPage.value = res.meta.last_page;
    } catch (e) {
        error.value = toApiError(e).message;
    } finally {
        loading.value = false;
        loadingMore.value = false;
    }
}

watch(() => route.query, () => {
    search.value = filters.value.q ?? '';
    void load();
});

onMounted(async () => {
    search.value = filters.value.q ?? '';
    void load();
    try {
        categories.value = await taxonomyApi.categories();
    } catch {
        /* без бічного меню каталог усе одно працює */
    }
});
</script>

<template>
    <section class="tx-linen bg-lavender-100">
        <div class="mx-auto max-w-[1280px] px-6 pt-10 pb-12 md:px-8">
            <nav v-if="activeCategory" aria-label="Шлях" class="mb-3 text-[15px] text-ink-soft">
                <RouterLink :to="{ name: 'catalog' }" class="text-plum">Каталог</RouterLink> / {{ activeCategory.name }}
            </nav>
            <h1 class="font-flair m-0 text-[44px] leading-tight font-bold tracking-[-0.02em] text-plum md:text-[56px]">
                {{ activeCategory?.name ?? 'Каталог' }}
            </h1>
            <form role="search" class="mt-8 flex max-w-[640px] flex-wrap gap-2" @submit.prevent="setFilter({ q: search.trim() || undefined })">
                <label for="catalog-q" class="sr-only">Пошук товарів</label>
                <input
                    id="catalog-q"
                    v-model="search"
                    type="search"
                    placeholder="Чашка, плед, схема вишивки…"
                    class="h-[52px] min-w-0 flex-[1_1_280px] rounded-control border-[1.5px] border-lavender-200 bg-white px-4 text-base text-ink focus:border-plum focus:outline-none"
                />
                <BaseButton type="submit" size="lg">Знайти</BaseButton>
            </form>
        </div>
        <OrnamentBand start="mountains" />
    </section>

    <div class="mx-auto grid max-w-[1280px] gap-10 px-6 py-12 md:px-8 lg:grid-cols-[240px_minmax(0,1fr)]">
        <!-- Категорії -->
        <aside aria-label="Категорії">
            <h2 class="m-0 mb-3 text-[15px] font-semibold text-ink-soft">Категорії</h2>
            <ul class="m-0 flex list-none flex-col gap-0.5 p-0 text-[15px]">
                <li>
                    <button
                        type="button"
                        class="w-full rounded-control px-3 py-2 text-left"
                        :class="!filters.category ? 'bg-plum font-semibold text-white' : 'text-plum hover:bg-lavender-50'"
                        @click="setFilter({ category: undefined })"
                    >
                        Усі товари
                    </button>
                </li>
                <li v-for="c in categories" :key="c.uuid">
                    <button
                        type="button"
                        class="w-full rounded-control px-3 py-2 text-left"
                        :class="filters.category === c.slug ? 'bg-plum font-semibold text-white' : 'text-plum hover:bg-lavender-50'"
                        :aria-current="filters.category === c.slug ? 'true' : undefined"
                        @click="setFilter({ category: c.slug })"
                    >
                        {{ c.name }}
                    </button>
                    <ul
                        v-if="c.children?.length && (filters.category === c.slug || c.children.some((ch) => ch.slug === filters.category))"
                        class="m-0 flex list-none flex-col gap-0.5 py-1 pl-4"
                    >
                        <li v-for="ch in c.children" :key="ch.uuid">
                            <button
                                type="button"
                                class="w-full rounded-control px-3 py-1.5 text-left text-[14px]"
                                :class="filters.category === ch.slug ? 'bg-lavender-50 font-semibold text-plum' : 'text-ink hover:bg-lavender-50'"
                                @click="setFilter({ category: ch.slug })"
                            >
                                {{ ch.name }}
                            </button>
                        </li>
                    </ul>
                </li>
            </ul>
        </aside>

        <section aria-live="polite" :aria-busy="loading">
            <!-- Тип і сортування -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap gap-2" role="group" aria-label="Тип товару">
                    <button
                        v-for="t in [{ id: undefined, label: 'Усі' }, { id: 'physical', label: 'Фізичні' }, { id: 'digital', label: 'Цифрові' }] as const"
                        :key="t.label"
                        type="button"
                        :aria-pressed="filters.type === t.id"
                        class="h-11 rounded-full border px-[18px] text-[15px]"
                        :class="filters.type === t.id ? 'border-plum bg-plum text-white' : 'border-line-strong bg-white text-ink hover:border-plum'"
                        @click="setFilter({ type: t.id })"
                    >
                        {{ t.label }}
                    </button>
                </div>
                <label class="flex items-center gap-2 text-[15px] text-ink-muted">
                    Сортувати
                    <select
                        :value="filters.sort"
                        class="h-11 rounded-control border-[1.5px] border-line-strong bg-white px-3 text-ink focus:border-plum focus:outline-none"
                        @change="setFilter({ sort: ($event.target as HTMLSelectElement).value })"
                    >
                        <option value="newest">Спочатку нові</option>
                        <option value="price_asc">Дешевші</option>
                        <option value="price_desc">Дорожчі</option>
                    </select>
                </label>
            </div>

            <div v-if="loading" class="mt-8 grid grid-cols-[repeat(auto-fill,minmax(220px,1fr))] gap-6" aria-label="Завантаження">
                <div v-for="i in 6" :key="i" class="flex animate-pulse flex-col gap-3">
                    <div class="aspect-square rounded-card bg-lavender-50" />
                    <div class="h-5 w-3/4 rounded bg-lavender-50" />
                </div>
            </div>

            <div v-else-if="error" role="alert" class="mt-8 flex flex-col items-start gap-4 rounded-card border border-kalyna/30 bg-kalyna/5 p-6">
                <p class="m-0 text-kalyna">{{ error }}</p>
                <BaseButton variant="secondary" @click="load()">Спробувати ще раз</BaseButton>
            </div>

            <div v-else-if="products.length === 0" class="mt-10 flex flex-col items-start gap-4">
                <h2 class="font-flair m-0 text-2xl font-bold text-plum">Нічого не знайшлося</h2>
                <p class="m-0 text-ink-muted">{{ hasFilters ? 'Спробуйте іншу категорію або змініть пошук.' : 'Товари з’являться, щойно майстри їх опублікують.' }}</p>
                <BaseButton v-if="hasFilters" variant="secondary" @click="router.replace({ query: {} })">Скинути фільтри</BaseButton>
            </div>

            <template v-else>
                <p class="mt-6 mb-6 text-ink-muted">{{ plural(total, ['товар', 'товари', 'товарів']) }}</p>
                <div class="grid grid-cols-[repeat(auto-fill,minmax(220px,1fr))] gap-x-6 gap-y-10">
                    <ProductCard v-for="p in products" :key="p.uuid" :product="p" />
                </div>
                <div v-if="page < lastPage" class="mt-12 flex justify-center">
                    <BaseButton variant="secondary" :loading="loadingMore" @click="page++; load(false)">Показати ще</BaseButton>
                </div>
            </template>
        </section>
    </div>
</template>
