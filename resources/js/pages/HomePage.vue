<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import AppHeader from '@/components/layout/AppHeader.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import OrnamentBand from '@/components/ornament/OrnamentBand.vue';
import StarOrnament from '@/components/ornament/StarOrnament.vue';
import StitchLine from '@/components/ornament/StitchLine.vue';
import SectionHeader from '@/components/home/SectionHeader.vue';
import MakerProfileCard from '@/components/makers/MakerProfileCard.vue';
import ProductCard from '@/components/products/ProductCard.vue';
import CourseCard from '@/components/home/CourseCard.vue';
import WorkshopRow from '@/components/home/WorkshopRow.vue';
import PostCard from '@/components/home/PostCard.vue';
import { courses, popularCrafts, posts, workshops } from '@/mocks/home';
import { productsApi } from '@/api/products';
import type { Product } from '@/types/product';
import { makersApi } from '@/api/makers';
import { useAuthStore } from '@/stores/auth';
import type { MakerProfile } from '@/types/maker';

const router = useRouter();
const auth = useAuthStore();
const query = ref('');

// Makers are real (from the API); other blocks still use sample data
const makers = ref<MakerProfile[]>([]);
const makersLoading = ref(true);
onMounted(async () => {
    try {
        makers.value = (await makersApi.list({ per_page: 4 })).data;
    } catch {
        makers.value = [];
    } finally {
        makersLoading.value = false;
    }
});

const becomeMakerLink = computed(() =>
    auth.isAuthenticated ? { name: 'studio' } : { name: 'register', query: { role: 'master' } },
);

type ProductFilter = 'all' | 'physical' | 'digital';
const productFilter = ref<ProductFilter>('all');
const productFilters: { id: ProductFilter; label: string }[] = [
    { id: 'all', label: 'Усі' },
    { id: 'physical', label: 'Фізичні' },
    { id: 'digital', label: 'Цифрові' },
];
const newProducts = ref<Product[]>([]);
const productsLoading = ref(true);
async function loadProducts() {
    productsLoading.value = true;
    try {
        const type = productFilter.value === 'all' ? undefined : productFilter.value;
        newProducts.value = (await productsApi.list({ type, per_page: 4 })).data;
    } catch {
        newProducts.value = [];
    } finally {
        productsLoading.value = false;
    }
}
watch(productFilter, loadProducts);
onMounted(loadProducts);

function search() {
    // TODO: search page /search?q=…
    void router.push({ path: '/', query: query.value ? { q: query.value } : {} });
}

const heroStitches = [
    'M-20 120 C 220 40, 420 210, 640 150 S 1080 30, 1300 110',
    'M1300 300 C 1150 300, 1060 470, 900 452 S 120 470, 40 380 S 60 300, 140 300',
];
</script>

<template>
    <!-- Hero -->
    <section class="tx-linen relative overflow-hidden bg-lavender-100 text-ink">
        <AppHeader />

        <div class="relative mx-auto max-w-[1280px] px-6 pt-16 pb-20 md:px-8 md:pt-[72px]">
            <StitchLine :paths="heroStitches" view-box="0 0 1280 520" />
            <StarOrnament :size="300" class="absolute top-12 right-10 hidden lg:block" />

            <div class="relative max-w-[820px]">
                <h1 class="font-flair m-0 text-[clamp(44px,6vw,84px)] leading-[1.02] font-bold tracking-[-0.025em] text-plum">
                    Речі, зроблені руками. І люди, які їх роблять.
                </h1>
                <p class="mt-6 mb-0 max-w-[600px] text-xl leading-normal text-ink-soft">
                    Купуйте у майстрів напряму, вчіться на їхніх курсах і приходьте на майстер-класи — онлайн чи у вашому місті.
                </p>
            </div>

            <form
                role="search"
                class="relative mt-12 flex max-w-[880px] flex-wrap gap-2 rounded-[20px] bg-white p-2 shadow-[0_0_0_6px_rgba(106,75,196,.14),0_12px_32px_rgba(58,42,102,.10)]"
                @submit.prevent="search"
            >
                <label for="hero-search" class="sr-only">Що шукаєте?</label>
                <div class="flex min-h-14 flex-[1_1_320px] items-center gap-3 px-4">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="text-ink-muted" aria-hidden="true">
                        <circle cx="11" cy="11" r="7" /><path d="M20 20l-4-4" />
                    </svg>
                    <input
                        id="hero-search"
                        v-model="query"
                        type="search"
                        placeholder="Пледи, керамічні чашки, курс з вишивки…"
                        class="min-w-0 flex-1 border-0 bg-transparent text-lg text-ink focus:outline-none"
                    />
                </div>
                <BaseButton type="submit" size="lg" class="min-h-14 !rounded-[14px]">Знайти</BaseButton>
            </form>

            <div class="relative mt-6 flex flex-wrap items-center gap-2.5">
                <span class="mr-1 text-[15px] text-ink-soft">Часто шукають:</span>
                <RouterLink
                    v-for="craft in popularCrafts"
                    :key="craft"
                    :to="{ path: '/', query: { q: craft } }"
                    class="flex h-10 items-center rounded-full border border-lavender-200 bg-white px-4 text-[15px] text-plum no-underline hover:border-violet"
                >
                    {{ craft }}
                </RouterLink>
            </div>
        </div>

        <OrnamentBand start="rhombus" />
    </section>

    <!-- Makers -->
    <section id="makers" aria-labelledby="makers-title" class="mx-auto max-w-[1280px] px-6 pt-24 md:px-8">
        <SectionHeader
            id="makers-title"
            title="Знайомтеся з майстрами"
            lead="Кожен профіль — це історія, майстерня і все, що людина створює."
            link-label="Усі майстри"
            :link-to="{ name: 'makers' }"
        />
        <div v-if="makersLoading" class="mt-10 grid grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-6" aria-busy="true" aria-label="Завантаження">
            <div v-for="i in 4" :key="i" class="aspect-[4/5] animate-pulse rounded-card bg-lavender-50" />
        </div>
        <div v-else-if="makers.length" class="mt-10 grid grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-6">
            <MakerProfileCard v-for="maker in makers" :key="maker.uuid" :maker="maker" />
        </div>
        <p v-else class="mt-8 mb-0 text-lg text-ink-muted">
            Перші майстри зʼявляться тут після перевірки профілів.
            <RouterLink :to="becomeMakerLink" class="font-semibold text-plum">Станьте одним із них</RouterLink>
        </p>
    </section>

    <!-- Products -->
    <section id="products" aria-labelledby="products-title" class="mx-auto max-w-[1280px] px-6 pt-28 md:px-8">
        <SectionHeader id="products-title" title="Нове в майстернях">
            <template #aside>
                <div class="flex flex-wrap gap-2" role="group" aria-label="Тип товару">
                    <button
                        v-for="f in productFilters"
                        :key="f.id"
                        type="button"
                        :aria-pressed="productFilter === f.id"
                        class="h-11 rounded-full border px-[18px] text-[15px]"
                        :class="productFilter === f.id ? 'border-plum bg-plum text-white' : 'border-line-strong bg-white text-ink hover:border-plum'"
                        @click="productFilter = f.id"
                    >
                        {{ f.label }}
                    </button>
                </div>
            </template>
        </SectionHeader>
        <div v-if="productsLoading" class="mt-10 grid grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-6" aria-busy="true" aria-label="Завантаження">
            <div v-for="i in 4" :key="i" class="aspect-square animate-pulse rounded-card bg-lavender-50" />
        </div>
        <div v-else-if="newProducts.length" class="mt-10 grid grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-x-6 gap-y-8">
            <ProductCard v-for="product in newProducts" :key="product.uuid" :product="product" />
        </div>
        <p v-else class="mt-8 mb-0 text-lg text-ink-muted">Нові товари зʼявляться тут, щойно майстри їх опублікують.</p>
        <div class="mt-10">
            <BaseButton variant="secondary" :to="{ name: 'catalog', query: productFilter === 'all' ? {} : { type: productFilter } }">Увесь каталог</BaseButton>
        </div>
    </section>

    <!-- Courses -->
    <section id="courses" aria-labelledby="courses-title" class="tx-weave mt-28 bg-lavender-50">
        <OrnamentBand start="tree" />
        <div class="mx-auto max-w-[1280px] px-6 py-[88px] md:px-8">
            <SectionHeader
                id="courses-title"
                title="Навчіться нового"
                lead="Відеоуроки й матеріали від майстрів. Проходьте у своєму темпі, прогрес зберігається."
                link-label="Усі курси"
                link-to="/"
            />
            <div class="mt-10 grid grid-cols-[repeat(auto-fill,minmax(min(340px,100%),1fr))] gap-6">
                <CourseCard v-for="course in courses" :key="course.id" :course="course" />
            </div>
        </div>
    </section>

    <!-- Workshops -->
    <section id="workshops" aria-labelledby="workshops-title" class="mx-auto max-w-[1280px] px-6 pt-24 md:px-8">
        <SectionHeader id="workshops-title" title="Найближчі майстер-класи" link-label="Календар" link-to="/" />
        <ul class="mt-10 mb-0 list-none border-t border-line p-0">
            <WorkshopRow v-for="workshop in workshops" :key="workshop.id" :workshop="workshop" />
        </ul>
    </section>

    <OrnamentBand start="barvinok" class="mt-24" />

    <!-- Community -->
    <section id="community" aria-labelledby="community-title" class="mx-auto max-w-[1280px] px-6 pt-[72px] md:px-8">
        <SectionHeader
            id="community-title"
            title="Зі спільноти"
            lead="Процес, ескізи й нові ідеї — те, що майстри показують до того, як річ потрапить у магазин."
        />
        <div class="mt-10 grid grid-cols-[repeat(auto-fill,minmax(min(380px,100%),1fr))] gap-6">
            <PostCard v-for="post in posts" :key="post.id" :post="post" />
        </div>
    </section>

    <!-- Become a maker -->
    <section class="mx-auto max-w-[1280px] px-6 py-28 md:px-8">
        <div class="tx-knit relative flex flex-wrap items-center justify-between gap-8 overflow-hidden rounded-panel bg-lavender-100 p-8 md:p-14">
            <StarOrnament :size="180" class="absolute -top-10 right-[300px] hidden lg:block" />
            <div class="relative max-w-[620px]">
                <h2 class="font-flair m-0 text-[30px] leading-tight font-bold text-plum md:text-4xl">Ви теж створюєте руками?</h2>
                <p class="mt-3.5 mb-0 text-lg leading-normal text-ink-soft">
                    Відкрийте майстерню: продавайте речі й файли, ведіть курси та майстер-класи. Після перевірки профілю він з'явиться в
                    каталозі.
                </p>
            </div>
            <BaseButton size="lg" :to="becomeMakerLink" class="relative">Стати майстром</BaseButton>
        </div>
    </section>
</template>
