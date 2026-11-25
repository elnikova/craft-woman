<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { INTL_LOCALE, currentLocale } from '@/i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import MakerAvatar from '@/components/makers/MakerAvatar.vue';
import MakerStatusBadge from '@/components/makers/MakerStatusBadge.vue';
import OrnamentBand from '@/components/ornament/OrnamentBand.vue';
import StarOrnament from '@/components/ornament/StarOrnament.vue';
import { makersApi } from '@/api/makers';
import { productsApi } from '@/api/products';
import ProductCard from '@/components/products/ProductCard.vue';
import type { Product } from '@/types/product';
import { toApiError } from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import type { MakerProfile } from '@/types/maker';

const props = defineProps<{ slug: string }>();
const auth = useAuthStore();
const { t } = useI18n();

const maker = ref<MakerProfile | null>(null);
const products = ref<Product[]>([]);
const loading = ref(true);
const notFound = ref(false);
const error = ref('');

const storyParagraphs = computed(() => (maker.value?.story ?? '').split(/\n{2,}/).filter(Boolean));
const since = computed(() =>
    maker.value?.approved_at
        ? new Intl.DateTimeFormat(INTL_LOCALE[currentLocale()], { month: 'long', year: 'numeric' }).format(new Date(maker.value.approved_at))
        : null,
);

async function load() {
    loading.value = true;
    notFound.value = false;
    error.value = '';
    try {
        maker.value = await makersApi.show(props.slug);
        document.title = `${maker.value.display_name} — Craft Woman`;
        products.value = maker.value.status && maker.value.status !== 'approved' ? [] : (await productsApi.list({ maker: props.slug, per_page: 12 })).data;
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
    <div v-if="loading" class="mx-auto max-w-[1280px] animate-pulse px-6 py-16 md:px-8" aria-busy="true" :aria-label="t('maker.loading')">
        <div class="h-12 w-1/2 rounded bg-lavender-50" />
        <div class="mt-4 h-5 w-1/4 rounded bg-lavender-50" />
    </div>

    <div v-else-if="notFound" class="mx-auto flex max-w-[1280px] flex-col items-start gap-4 px-6 py-20 md:px-8">
        <h1 class="font-flair m-0 text-4xl font-bold text-plum">{{ t('maker.notFoundTitle') }}</h1>
        <p class="m-0 text-lg text-ink-muted">{{ t('maker.notFoundText') }}</p>
        <BaseButton :to="{ name: 'makers' }">{{ t('maker.allMakers') }}</BaseButton>
    </div>

    <div v-else-if="error" role="alert" class="mx-auto flex max-w-[1280px] flex-col items-start gap-4 px-6 py-20 md:px-8">
        <p class="m-0 text-kalyna">{{ error }}</p>
        <BaseButton variant="secondary" @click="load">{{ t('common.retry') }}</BaseButton>
    </div>

    <template v-else-if="maker">
        <!-- The owner or an administrator sees that the profile is not public yet -->
        <div v-if="maker.status && maker.status !== 'approved'" class="bg-notice-bg">
            <div class="mx-auto flex max-w-[1280px] flex-wrap items-center gap-3 px-6 py-3 text-notice-ink md:px-8">
                <MakerStatusBadge :status="maker.status" />
                <template v-if="auth.isAdmin">
                    <span>{{ t('maker.unpublishedAdmin') }}</span>
                    <RouterLink :to="{ name: 'admin-makers' }" class="font-semibold text-notice-ink">{{ t('maker.toReview') }}</RouterLink>
                </template>
                <template v-else>
                    <span>{{ t('maker.unpublishedOwner') }}</span>
                    <RouterLink :to="{ name: 'studio' }" class="font-semibold text-notice-ink">{{ t('maker.toStudio') }}</RouterLink>
                </template>
            </div>
        </div>

        <section class="tx-linen relative overflow-hidden bg-lavender-100">
            <StarOrnament :size="260" class="absolute top-10 right-10 hidden lg:block" />
            <div class="relative mx-auto flex max-w-[1280px] flex-wrap items-end gap-10 px-6 pt-12 pb-14 md:px-8">
                <MakerAvatar :uuid="maker.uuid" :name="maker.display_name" :src="maker.avatar_url" class="w-[220px] shrink-0" />
                <div class="min-w-0 flex-[1_1_360px]">
                    <h1 class="font-flair m-0 text-[44px] leading-tight font-bold tracking-[-0.02em] text-plum md:text-[60px]">
                        {{ maker.display_name }}
                    </h1>
                    <p class="mt-3 mb-0 flex flex-wrap gap-x-4 gap-y-1 text-lg text-ink-soft">
                        <span v-if="maker.city">{{ maker.city }}</span>
                        <span v-if="since">{{ t('maker.since', { date: since }) }}</span>
                    </p>
                    <p v-if="maker.bio" class="mt-5 mb-0 max-w-[620px] text-xl leading-normal text-ink">{{ maker.bio }}</p>
                    <div class="mt-7 flex flex-wrap gap-3">
                        <BaseButton v-if="maker.follow_enabled" disabled :title="t('maker.soon')">{{ t('maker.follow') }}</BaseButton>
                        <BaseButton variant="secondary" :to="{ name: 'makers' }">{{ t('maker.allMakers') }}</BaseButton>
                    </div>
                </div>
            </div>
            <OrnamentBand start="barvinok" />
        </section>

        <div class="mx-auto grid max-w-[1280px] gap-16 px-6 py-16 md:px-8 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <section aria-labelledby="story-title">
                <h2 id="story-title" class="font-flair m-0 text-3xl font-bold text-plum">{{ t('maker.story') }}</h2>
                <div v-if="storyParagraphs.length" class="mt-5 flex max-w-[68ch] flex-col gap-4 text-[17px] leading-relaxed text-ink" :lang="maker.content_locale">
                    <p v-for="(p, i) in storyParagraphs" :key="i" class="m-0">{{ p }}</p>
                </div>
                <p v-else class="mt-5 mb-0 text-ink-muted">{{ t('maker.noStory') }}</p>
            </section>

            <aside class="flex flex-col gap-4 self-start rounded-card bg-lavender-50 p-6">
                <h2 class="m-0 text-lg font-semibold text-plum">{{ t('maker.soonHere') }}</h2>
                <p class="m-0 text-ink-muted">{{ t('maker.soonText') }}</p>
            </aside>
        </div>

        <section class="mx-auto max-w-[1280px] px-6 pb-20 md:px-8" aria-labelledby="maker-products">
            <h2 id="maker-products" class="font-flair m-0 text-3xl font-bold text-plum">{{ t('maker.works') }}</h2>
            <div v-if="products.length" class="mt-8 grid grid-cols-[repeat(auto-fill,minmax(220px,1fr))] gap-x-6 gap-y-10">
                <ProductCard v-for="p in products" :key="p.uuid" :product="p" />
            </div>
            <p v-else class="mt-5 mb-0 text-ink-muted">{{ t('maker.noProducts') }}</p>
        </section>
    </template>
</template>
