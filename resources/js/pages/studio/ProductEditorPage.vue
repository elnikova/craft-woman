<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { onBeforeRouteLeave, useRouter } from 'vue-router';
import ContentLanguage from '@/components/ui/ContentLanguage.vue';
import type { Locale } from '@/i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseInput from '@/components/ui/BaseInput.vue';
import BaseTextarea from '@/components/ui/BaseTextarea.vue';
import ProductStatusBadge from '@/components/products/ProductStatusBadge.vue';
import { productsApi } from '@/api/products';
import { taxonomyApi } from '@/api/taxonomy';
import { toApiError, type FieldErrors } from '@/api/http';
import type { Currency, Product, ProductPayload, ProductType } from '@/types/product';
import type { Category } from '@/types/taxonomy';
import { formatFileSize, inputToMinor, minorToInput } from '@/utils/format';

const props = defineProps<{ uuid?: string }>();
const router = useRouter();
const { t } = useI18n();

interface VariantRow {
    uuid: string | null;
    name: string;
    sku: string;
    price: string;
    stock: string;
    weight: string;
    active: boolean;
}

const product = ref<Product | null>(null);
const categories = ref<Category[]>([]);
const loading = ref(true);
const loadError = ref('');

const form = reactive({
    type: 'physical' as ProductType,
    name: '',
    description: '',
    price: '',
    currency: 'EUR' as Currency,
    category_uuid: '' as string,
    tags: '',
    production_time_days: '',
    shipping_info: '',
    variants: [] as VariantRow[],
    // Source language and the optional version in the other language
    content_locale: 'uk' as Locale,
    tr_name: '',
    tr_description: '',
    tr_shipping_info: '',
});
const otherLocale = computed<Locale>(() => (form.content_locale === 'uk' ? 'en' : 'uk'));
const typeOptions = computed(() => [
    { id: 'physical' as const, title: t('editor.physical'), text: t('editor.physicalText') },
    { id: 'digital' as const, title: t('editor.digital'), text: t('editor.digitalText') },
]);
const saved = ref('');
const dirty = computed(() => JSON.stringify(form) !== saved.value);

const errors = ref<FieldErrors>({});
const formError = ref('');
const notice = ref('');
const busy = ref<string | null>(null);
const uploadProgress = ref<number | null>(null);
const confirmDelete = ref(false);

const isNew = computed(() => !product.value);
const status = computed(() => product.value?.status ?? 'draft');
const editable = computed(() => isNew.value || product.value?.is_editable === true);
const typeLocked = computed(() => !isNew.value && (status.value !== 'draft' || !!product.value?.published_at));

function emptyVariant(name = ''): VariantRow {
    return { uuid: null, name, sku: '', price: '', stock: '1', weight: '', active: true };
}

function fill(p: Product) {
    product.value = p;
    Object.assign(form, {
        type: p.type,
        name: p.name,
        description: p.description ?? '',
        price: minorToInput(p.price.amount_minor || null),
        currency: p.price.currency,
        category_uuid: p.category?.uuid ?? '',
        tags: p.tags.join(', '),
        production_time_days: p.production_time_days?.toString() ?? '',
        shipping_info: p.shipping_info ?? '',
        content_locale: p.content_locale ?? 'uk',
        tr_name: p.translations?.[p.content_locale === 'en' ? 'uk' : 'en']?.name ?? '',
        tr_description: p.translations?.[p.content_locale === 'en' ? 'uk' : 'en']?.description ?? '',
        tr_shipping_info: p.translations?.[p.content_locale === 'en' ? 'uk' : 'en']?.shipping_info ?? '',
        variants: p.variants.map((v) => ({
            uuid: v.uuid,
            name: v.name,
            sku: v.sku ?? '',
            price: minorToInput(v.price_override_minor ?? null),
            stock: String(v.stock ?? 0),
            weight: v.weight_grams?.toString() ?? '',
            active: v.status !== 'inactive',
        })),
    });
    saved.value = JSON.stringify(form);
}

function payload(): ProductPayload | null {
    const price = inputToMinor(form.price);
    const local: FieldErrors = {};
    if (Number.isNaN(price)) local.price_amount_minor = t('editor.priceFormat');
    form.variants.forEach((v, i) => {
        if (Number.isNaN(inputToMinor(v.price))) local[`variants.${i}.price_amount_minor`] = t('editor.badPrice');
        if (!/^\d+$/.test(v.stock.trim())) local[`variants.${i}.stock`] = t('editor.badStock');
    });
    if (Object.keys(local).length) {
        errors.value = local;
        formError.value = t('editor.checkFields');
        return null;
    }
    const num = (s: string) => (s.trim() ? Number(s) : null);
    return {
        type: form.type,
        name: form.name.trim(),
        description: form.description.trim() || null,
        price_amount_minor: price ?? 0,
        currency: form.currency,
        production_time_days: form.type === 'physical' ? num(form.production_time_days) : null,
        shipping_info: form.type === 'physical' ? form.shipping_info.trim() || null : null,
        category_uuid: form.category_uuid || null,
        tags: form.tags.split(',').map((tag) => tag.trim()).filter(Boolean),
        content_locale: form.content_locale,
        translations: {
            [otherLocale.value]: {
                name: form.tr_name.trim() || null,
                description: form.tr_description.trim() || null,
                shipping_info: form.type === 'physical' ? form.tr_shipping_info.trim() || null : null,
            },
        },
        ...(form.type === 'physical' && form.variants.length
            ? {
                  variants: form.variants.map((v) => ({
                      uuid: v.uuid,
                      name: v.name.trim(),
                      sku: v.sku.trim() || null,
                      price_amount_minor: inputToMinor(v.price),
                      stock: Number(v.stock),
                      weight_grams: num(v.weight),
                      status: v.active ? 'active' : 'inactive',
                  })),
              }
            : {}),
    };
}

async function load() {
    loading.value = true;
    loadError.value = '';
    try {
        const [cats, p] = await Promise.all([taxonomyApi.categories(), props.uuid ? productsApi.mineOne(props.uuid) : Promise.resolve(null)]);
        categories.value = cats;
        if (p) fill(p);
        else {
            form.variants = [emptyVariant(t('editor.defaultVariant'))];
            saved.value = JSON.stringify(form);
        }
    } catch (e) {
        loadError.value = toApiError(e).message;
    } finally {
        loading.value = false;
    }
}

async function run<T>(key: string, fn: () => Promise<T>, success?: string): Promise<T | null> {
    busy.value = key;
    formError.value = '';
    notice.value = '';
    try {
        const result = await fn();
        if (success) notice.value = success;
        return result;
    } catch (e) {
        const err = toApiError(e);
        errors.value = err.fields;
        formError.value = Object.keys(err.fields).length ? t('editor.checkFields') : err.message;
        return null;
    } finally {
        busy.value = null;
    }
}

async function save(): Promise<boolean> {
    errors.value = {};
    const body = payload();
    if (!body) return false;
    const wasNew = isNew.value;
    const p = await run('save', () => (product.value ? productsApi.update(product.value.uuid, body) : productsApi.create(body)), t('editor.saved'));
    if (!p) return false;
    fill(p);
    if (wasNew) {
        notice.value = t('editor.draftCreated');
        await router.replace({ name: 'studio-product', params: { uuid: p.uuid } });
    }
    return true;
}

async function submit() {
    if ((dirty.value || isNew.value) && !(await save())) return;
    const p = await run('submit', () => productsApi.submit(product.value!.uuid), t('editor.submitted'));
    if (p) fill(p);
}

async function lifecycle(action: 'archive' | 'relist') {
    const p = await run(action, () => productsApi[action](product.value!.uuid), action === 'archive' ? t('editor.archived') : t('editor.relisted'));
    if (p) fill(p);
}

async function destroy() {
    const ok = await run('delete', () => productsApi.remove(product.value!.uuid));
    if (ok !== null) {
        saved.value = JSON.stringify(form);
        await router.push({ name: 'studio-products' });
    }
}

// ---- photos and files (saved immediately) ----
async function onImages(e: Event) {
    const files = [...((e.target as HTMLInputElement).files ?? [])];
    (e.target as HTMLInputElement).value = '';
    for (const file of files) {
        uploadProgress.value = 0;
        const p = await run('image', () => productsApi.uploadImage(product.value!.uuid, file, (pct) => (uploadProgress.value = pct)));
        if (!p) break;
        product.value = p;
    }
    uploadProgress.value = null;
}

async function moveImage(i: number, dir: -1 | 1) {
    const imgs = [...product.value!.images];
    const [img] = imgs.splice(i, 1);
    imgs.splice(i + dir, 0, img!);
    const p = await run('order', () => productsApi.reorderImages(product.value!.uuid, imgs.map((x) => x.uuid)));
    if (p) product.value = p;
}

async function removeImage(uuid: string) {
    const p = await run('image', () => productsApi.deleteImage(product.value!.uuid, uuid));
    if (p) product.value = p;
}

async function onFile(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    (e.target as HTMLInputElement).value = '';
    if (!file) return;
    uploadProgress.value = 0;
    const p = await run('file', () => productsApi.uploadFile(product.value!.uuid, file, (pct) => (uploadProgress.value = pct)));
    uploadProgress.value = null;
    if (p) product.value = p;
}

async function removeFile(uuid: string) {
    const p = await run('file', () => productsApi.deleteFile(product.value!.uuid, uuid));
    if (p) product.value = p;
}

watch(() => props.uuid, (u) => { if (u && u !== product.value?.uuid) void load(); });

function beforeUnload(e: BeforeUnloadEvent) {
    if (dirty.value) e.preventDefault();
}
onMounted(() => {
    void load();
    window.addEventListener('beforeunload', beforeUnload);
});
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));
onBeforeRouteLeave((to) => (dirty.value && to.name !== 'studio-product' ? window.confirm(t('editor.leaveConfirm')) : true));
</script>

<template>
    <div class="max-w-[920px]">
        <RouterLink :to="{ name: 'studio-products' }" class="text-[15px] text-plum">{{ t('editor.allProducts') }}</RouterLink>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-4">
            <h1 class="font-flair m-0 text-[36px] leading-tight font-bold tracking-[-0.015em] text-plum">{{ isNew ? t('editor.newProduct') : form.name || t('editor.product') }}</h1>
            <ProductStatusBadge v-if="product?.status" :status="product.status" />
        </div>

        <div v-if="loading" class="mt-8 space-y-4" aria-busy="true" :aria-label="t('common.loading')">
            <div v-for="i in 4" :key="i" class="h-14 animate-pulse rounded-control bg-lavender-50" />
        </div>

        <div v-else-if="loadError" role="alert" class="mt-8 flex flex-col items-start gap-4 rounded-card border border-kalyna/30 bg-kalyna/5 p-6">
            <p class="m-0 text-kalyna">{{ loadError }}</p>
            <BaseButton variant="secondary" @click="load">{{ t('common.retry') }}</BaseButton>
        </div>

        <template v-else>
            <!-- What is happening with the product -->
            <div v-if="product" class="mt-6 rounded-card p-5" :class="status === 'rejected' ? 'bg-kalyna/5' : 'bg-lavender-50'">
                <p v-if="status === 'draft'" class="m-0 text-ink">{{ t('editor.draftHint') }}</p>
                <p v-else-if="status === 'pending_review'" class="m-0 text-ink">{{ t('editor.pendingHint') }}</p>
                <p v-else-if="status === 'published'" class="m-0 text-ink">
                    {{ t('editor.publishedHint') }}
                    <RouterLink :to="{ name: 'product', params: { slug: product.slug } }" class="font-semibold text-plum">{{ t('editor.openPage') }}</RouterLink>.
                    {{ t('editor.publishedEdits') }}
                </p>
                <template v-else-if="status === 'rejected'">
                    <p class="m-0 font-semibold text-kalyna">{{ t('editor.changesNeeded') }}</p>
                    <p v-if="product.review_note" class="mt-1 mb-0 text-ink"><span class="font-semibold">{{ t('editor.moderatorNote') }}</span> {{ product.review_note }}</p>
                </template>
                <p v-else-if="status === 'archived'" class="m-0 text-ink">
                    {{ t('editor.archivedHint', { note: product.review_note ? ` (${product.review_note})` : '' }) }}
                </p>
            </div>

            <form class="mt-8 flex flex-col gap-10" novalidate @submit.prevent="save">
                <fieldset :disabled="!editable" class="m-0 flex flex-col gap-10 border-0 p-0">
                    <!-- Basics -->
                    <section class="flex flex-col gap-6" aria-labelledby="sec-main">
                        <h2 id="sec-main" class="m-0 text-xl font-semibold text-plum">{{ t('editor.basics') }}</h2>
                        <div role="radiogroup" :aria-label="t('editor.type')" class="grid gap-3 sm:grid-cols-2">
                            <label
                                v-for="opt in typeOptions"
                                :key="opt.id"
                                class="flex cursor-pointer gap-3 rounded-control border-2 p-4"
                                :class="[form.type === opt.id ? 'border-plum bg-lavender-50' : 'border-line', typeLocked ? 'cursor-not-allowed opacity-70' : '']"
                            >
                                <input v-model="form.type" type="radio" :value="opt.id" :disabled="typeLocked" class="mt-1 h-5 w-5 accent-plum" />
                                <span>
                                    <span class="block font-semibold text-ink">{{ opt.title }}</span>
                                    <span class="block text-sm text-ink-muted">{{ opt.text }}</span>
                                </span>
                            </label>
                        </div>
                        <p v-if="typeLocked" class="-mt-3 mb-0 text-[13px] text-ink-muted">{{ t('editor.typeLocked') }}</p>

                        <BaseInput v-model="form.name" :label="t('editor.name')" :maxlength="160" required :error="errors.name" />
                        <BaseTextarea
                            v-model="form.description"
                            :label="t('editor.description')"
                            :rows="6"
                            :maxlength="10000"
                            :hint="t('editor.descriptionHint')"
                            :error="errors.description"
                        />

                        <ContentLanguage v-model="form.content_locale" />
                        <fieldset class="m-0 flex flex-col gap-5 rounded-card border border-line p-5" :lang="otherLocale">
                            <legend class="px-1 text-[15px] font-semibold text-plum">
                                {{ t('content.translation', { lang: t(`content.langName.${otherLocale}`) }) }}
                            </legend>
                            <p class="m-0 text-[13px] text-ink-muted">{{ t('content.translationHint') }}</p>
                            <BaseInput v-model="form.tr_name" :label="t('editor.name')" :maxlength="160" :error="errors[`translations.${otherLocale}.name`]" />
                            <BaseTextarea
                                v-model="form.tr_description"
                                :label="t('editor.description')"
                                :rows="5"
                                :maxlength="10000"
                                :error="errors[`translations.${otherLocale}.description`]"
                            />
                            <BaseTextarea
                                v-if="form.type === 'physical'"
                                v-model="form.tr_shipping_info"
                                :label="t('editor.delivery')"
                                :rows="2"
                                :maxlength="1000"
                                :error="errors[`translations.${otherLocale}.shipping_info`]"
                            />
                        </fieldset>

                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="flex flex-col gap-2">
                                <label for="category" class="text-[15px] font-semibold text-ink">{{ t('editor.category') }}</label>
                                <select
                                    id="category"
                                    v-model="form.category_uuid"
                                    class="h-[52px] rounded-control border-[1.5px] bg-white px-4 text-base text-ink focus:border-plum focus:outline-none disabled:bg-lavender-50"
                                    :class="errors.category_uuid ? 'border-kalyna' : 'border-line-strong'"
                                >
                                    <option value="">{{ t('editor.chooseCategory') }}</option>
                                    <optgroup v-for="c in categories" :key="c.uuid" :label="c.name">
                                        <option :value="c.uuid">{{ t('editor.general', { name: c.name }) }}</option>
                                        <option v-for="ch in c.children" :key="ch.uuid" :value="ch.uuid">{{ ch.name }}</option>
                                    </optgroup>
                                </select>
                                <p v-if="errors.category_uuid" class="m-0 text-sm font-medium text-kalyna">{{ errors.category_uuid }}</p>
                            </div>
                            <BaseInput v-model="form.tags" :label="t('editor.tags')" :placeholder="t('editor.tagsPlaceholder')" :hint="t('editor.tagsHint')" :error="errors.tags" />
                        </div>
                    </section>

                    <!-- Price -->
                    <section class="flex flex-col gap-6" aria-labelledby="sec-price">
                        <h2 id="sec-price" class="m-0 text-xl font-semibold text-plum">{{ t('editor.price') }}</h2>
                        <div class="grid max-w-[480px] grid-cols-[minmax(0,1fr)_120px] gap-4">
                            <BaseInput v-model="form.price" :label="t('editor.basePrice')" placeholder="28,50" :error="errors.price_amount_minor" />
                            <div class="flex flex-col gap-2">
                                <label for="currency" class="text-[15px] font-semibold text-ink">{{ t('editor.currency') }}</label>
                                <select id="currency" v-model="form.currency" class="h-[52px] rounded-control border-[1.5px] border-line-strong bg-white px-3 text-base text-ink focus:border-plum focus:outline-none disabled:bg-lavender-50">
                                    <option value="EUR">EUR</option>
                                    <option value="USD">USD</option>
                                    <option value="UAH">UAH</option>
                                </select>
                            </div>
                        </div>
                    </section>

                    <!-- Variants -->
                    <section v-if="form.type === 'physical'" class="flex flex-col gap-4" aria-labelledby="sec-variants">
                        <div>
                            <h2 id="sec-variants" class="m-0 text-xl font-semibold text-plum">{{ t('editor.variants') }}</h2>
                            <p class="mt-1 mb-0 text-[15px] text-ink-muted">{{ t('editor.variantsHint') }}</p>
                        </div>
                        <div class="overflow-x-auto rounded-card border border-line">
                            <table class="w-full min-w-[640px] border-collapse text-[15px]">
                                <thead>
                                    <tr class="bg-lavender-50 text-left text-[13px] text-ink-soft">
                                        <th scope="col" class="px-3 py-2 font-semibold">{{ t('editor.colName') }}</th>
                                        <th scope="col" class="px-3 py-2 font-semibold">{{ t('editor.colQty') }}</th>
                                        <th scope="col" class="px-3 py-2 font-semibold">{{ t('editor.colPrice', { currency: form.currency }) }}</th>
                                        <th scope="col" class="px-3 py-2 font-semibold">{{ t('editor.colWeight') }}</th>
                                        <th scope="col" class="px-3 py-2 font-semibold">{{ t('editor.colSku') }}</th>
                                        <th scope="col" class="px-3 py-2 font-semibold">{{ t('editor.colActive') }}</th>
                                        <th scope="col" class="px-3 py-2"><span class="sr-only">{{ t('editor.colActions') }}</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(v, i) in form.variants" :key="v.uuid ?? `new-${i}`" class="border-t border-line align-top">
                                        <td class="px-3 py-2">
                                            <input v-model="v.name" :aria-label="t('editor.variantName', { n: i + 1 })" maxlength="120" class="h-11 w-full rounded-[10px] border-[1.5px] px-3 focus:border-plum focus:outline-none" :class="errors[`variants.${i}.name`] ? 'border-kalyna' : 'border-line-strong'" />
                                        </td>
                                        <td class="px-3 py-2">
                                            <input v-model="v.stock" inputmode="numeric" :aria-label="t('editor.variantQty', { n: i + 1 })" class="h-11 w-20 rounded-[10px] border-[1.5px] px-3 focus:border-plum focus:outline-none" :class="errors[`variants.${i}.stock`] ? 'border-kalyna' : 'border-line-strong'" />
                                        </td>
                                        <td class="px-3 py-2">
                                            <input v-model="v.price" inputmode="decimal" :placeholder="form.price || '—'" :aria-label="t('editor.variantPrice', { n: i + 1 })" class="h-11 w-24 rounded-[10px] border-[1.5px] px-3 focus:border-plum focus:outline-none" :class="errors[`variants.${i}.price_amount_minor`] ? 'border-kalyna' : 'border-line-strong'" />
                                        </td>
                                        <td class="px-3 py-2">
                                            <input v-model="v.weight" inputmode="numeric" :aria-label="t('editor.variantWeight', { n: i + 1 })" class="h-11 w-20 rounded-[10px] border-[1.5px] border-line-strong px-3 focus:border-plum focus:outline-none" />
                                        </td>
                                        <td class="px-3 py-2">
                                            <input v-model="v.sku" maxlength="64" :aria-label="t('editor.variantSku', { n: i + 1 })" class="h-11 w-28 rounded-[10px] border-[1.5px] border-line-strong px-3 focus:border-plum focus:outline-none" />
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <input v-model="v.active" type="checkbox" :aria-label="t('editor.variantActive', { n: i + 1 })" class="mt-3 h-5 w-5 accent-plum" />
                                        </td>
                                        <td class="px-3 py-2">
                                            <button
                                                v-if="form.variants.length > 1"
                                                type="button"
                                                class="h-11 rounded-full px-3 text-sm text-kalyna hover:bg-kalyna/5"
                                                :aria-label="t('editor.removeVariant', { name: v.name || i + 1 })"
                                                @click="form.variants.splice(i, 1)"
                                            >{{ t('editor.remove') }}</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-for="(msg, key) in Object.fromEntries(Object.entries(errors).filter(([k]) => k.startsWith('variants.')))" :key="key" class="m-0 text-sm text-kalyna">
                            {{ t('editor.variantError', { n: Number(String(key).split('.')[1]) + 1, msg }) }}
                        </p>
                        <div><BaseButton variant="secondary" @click="form.variants.push(emptyVariant())">{{ t('editor.addVariant') }}</BaseButton></div>
                    </section>

                    <!-- Delivery -->
                    <section v-if="form.type === 'physical'" class="flex flex-col gap-6" aria-labelledby="sec-shipping">
                        <h2 id="sec-shipping" class="m-0 text-xl font-semibold text-plum">{{ t('editor.shipping') }}</h2>
                        <div class="max-w-[280px]">
                            <BaseInput v-model="form.production_time_days" :label="t('editor.productionDays')" :hint="t('editor.productionHint')" :error="errors.production_time_days" />
                        </div>
                        <BaseTextarea v-model="form.shipping_info" :label="t('editor.delivery')" :rows="3" :maxlength="1000" :placeholder="t('editor.deliveryPlaceholder')" :error="errors.shipping_info" />
                    </section>
                </fieldset>

                <!-- Photos -->
                <section class="flex flex-col gap-4" aria-labelledby="sec-images">
                    <div>
                        <h2 id="sec-images" class="m-0 text-xl font-semibold text-plum">{{ t('editor.photos') }}</h2>
                        <p class="mt-1 mb-0 text-[15px] text-ink-muted">{{ t('editor.photosHint') }}</p>
                    </div>
                    <p v-if="isNew" class="m-0 rounded-[10px] bg-lavender-50 px-4 py-3 text-[15px] text-plum">{{ t('editor.saveFirstPhotos') }}</p>
                    <template v-else-if="product">
                        <ul v-if="product.images.length" class="m-0 grid list-none grid-cols-[repeat(auto-fill,minmax(150px,1fr))] gap-4 p-0">
                            <li v-for="(img, i) in product.images" :key="img.uuid" class="flex flex-col gap-2">
                                <div class="relative">
                                    <img :src="img.url" :alt="img.alt" class="aspect-square w-full rounded-card object-cover" />
                                    <span v-if="i === 0" class="absolute top-2 left-2 rounded-lg bg-plum px-2 py-1 text-[12px] font-semibold text-white">{{ t('editor.cover') }}</span>
                                </div>
                                <div v-if="editable" class="flex gap-1">
                                    <button type="button" class="h-9 flex-1 rounded-full border border-line-strong text-sm text-plum disabled:opacity-40" :disabled="i === 0 || !!busy" :aria-label="t('editor.moveLeft', { n: i + 1 })" @click="moveImage(i, -1)">←</button>
                                    <button type="button" class="h-9 flex-1 rounded-full border border-line-strong text-sm text-plum disabled:opacity-40" :disabled="i === product.images.length - 1 || !!busy" :aria-label="t('editor.moveRight', { n: i + 1 })" @click="moveImage(i, 1)">→</button>
                                    <button type="button" class="h-9 flex-1 rounded-full border border-kalyna/40 text-sm text-kalyna disabled:opacity-40" :disabled="!!busy" :aria-label="t('editor.deletePhoto', { n: i + 1 })" @click="removeImage(img.uuid)">✕</button>
                                </div>
                            </li>
                        </ul>
                        <label v-if="editable && product.images.length < 10" class="flex h-[52px] w-fit cursor-pointer items-center gap-2 rounded-full border-[1.5px] border-plum px-5 font-semibold text-plum hover:bg-lavender-50">
                            <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" :disabled="!!busy" @change="onImages" />
                            {{ busy === 'image' && uploadProgress !== null ? t('editor.uploading', { n: uploadProgress }) : t('editor.addPhoto') }}
                        </label>
                    </template>
                </section>

                <!-- Files -->
                <section v-if="form.type === 'digital'" class="flex flex-col gap-4" aria-labelledby="sec-files">
                    <div>
                        <h2 id="sec-files" class="m-0 text-xl font-semibold text-plum">{{ t('editor.files') }}</h2>
                        <p class="mt-1 mb-0 text-[15px] text-ink-muted">{{ t('editor.filesHint') }}</p>
                    </div>
                    <p v-if="isNew" class="m-0 rounded-[10px] bg-lavender-50 px-4 py-3 text-[15px] text-plum">{{ t('editor.saveFirstFiles') }}</p>
                    <template v-else-if="product">
                        <ul v-if="product.files?.length" class="m-0 flex list-none flex-col gap-2 p-0">
                            <li v-for="f in product.files" :key="f.uuid" class="flex items-center gap-3 rounded-control border border-line px-4 py-3">
                                <span class="min-w-0 flex-1 truncate font-medium text-ink">{{ f.name }}</span>
                                <span class="text-sm text-ink-muted">{{ formatFileSize(f.size) }}</span>
                                <button v-if="editable" type="button" class="h-9 rounded-full px-3 text-sm text-kalyna hover:bg-kalyna/5" :disabled="!!busy" @click="removeFile(f.uuid)">{{ t('editor.delete') }}</button>
                            </li>
                        </ul>
                        <label v-if="editable" class="flex h-[52px] w-fit cursor-pointer items-center gap-2 rounded-full border-[1.5px] border-plum px-5 font-semibold text-plum hover:bg-lavender-50">
                            <input type="file" accept=".pdf,.zip,.epub,.txt,.jpg,.jpeg,.png,.svg" class="sr-only" :disabled="!!busy" @change="onFile" />
                            {{ busy === 'file' && uploadProgress !== null ? t('editor.uploading', { n: uploadProgress }) : t('editor.addFile') }}
                        </label>
                    </template>
                </section>

                <div v-if="formError" role="alert" class="rounded-[10px] border border-kalyna/30 bg-kalyna/5 px-3.5 py-3 text-[15px] text-kalyna">{{ formError }}</div>
                <p v-else-if="notice" role="status" class="m-0 text-[15px] font-semibold text-[#2F4A1C]">{{ notice }}</p>

                <!-- Actions -->
                <div class="flex flex-wrap gap-3 border-t border-line pt-6">
                    <template v-if="editable">
                        <BaseButton type="submit" size="lg" :variant="status === 'draft' || status === 'rejected' ? 'secondary' : 'primary'" :loading="busy === 'save'" :disabled="!dirty && !isNew">
                            {{ isNew ? t('editor.createDraft') : t('editor.save') }}
                        </BaseButton>
                        <BaseButton v-if="!isNew && (status === 'draft' || status === 'rejected')" size="lg" :loading="busy === 'submit'" @click="submit">{{ t('editor.submit') }}</BaseButton>
                    </template>
                    <BaseButton v-if="status === 'published' && product" variant="ghost" :loading="busy === 'archive'" @click="lifecycle('archive')">{{ t('editor.archive') }}</BaseButton>
                    <BaseButton v-if="status === 'archived'" :loading="busy === 'relist'" @click="lifecycle('relist')">{{ t('editor.relist') }}</BaseButton>
                    <template v-if="product?.is_deletable">
                        <span class="flex-1" />
                        <template v-if="confirmDelete">
                            <span class="self-center text-[15px] text-ink">{{ t('editor.deleteConfirm') }}</span>
                            <BaseButton variant="secondary" :loading="busy === 'delete'" @click="destroy">{{ t('editor.yesDelete') }}</BaseButton>
                            <BaseButton variant="ghost" @click="confirmDelete = false">{{ t('editor.no') }}</BaseButton>
                        </template>
                        <BaseButton v-else variant="ghost" @click="confirmDelete = true">{{ t('editor.deleteDraft') }}</BaseButton>
                    </template>
                </div>
            </form>
        </template>
    </div>
</template>
