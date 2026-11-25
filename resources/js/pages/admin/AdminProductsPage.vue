<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseTextarea from '@/components/ui/BaseTextarea.vue';
import ProductCover from '@/components/products/ProductCover.vue';
import { productsApi } from '@/api/products';
import { toApiError } from '@/api/http';
import type { Product, ProductStatus } from '@/types/product';
import { formatPrice } from '@/utils/format';
import { PRODUCT_STATUS_LABELS } from '@/utils/productStatus';

const TABS: ProductStatus[] = ['pending_review', 'published', 'rejected', 'archived'];
const tab = ref<ProductStatus>('pending_review');
const items = ref<Product[]>([]);
const loading = ref(true);
const error = ref('');
const flash = ref('');
const busy = ref<string | null>(null);
const noteFor = ref<{ uuid: string; action: 'reject' | 'archive' } | null>(null);
const note = ref('');
const noteError = ref('');
const expanded = ref<string | null>(null);

async function load() {
    loading.value = true;
    error.value = '';
    try {
        items.value = (await productsApi.reviewQueue(tab.value)).data;
    } catch (e) {
        error.value = toApiError(e).message;
    } finally {
        loading.value = false;
    }
}

watch(tab, () => {
    noteFor.value = null;
    flash.value = '';
    void load();
}, { immediate: true });

function drop(p: Product, message: string) {
    items.value = items.value.filter((x) => x.uuid !== p.uuid);
    flash.value = message;
}

async function approve(p: Product) {
    busy.value = p.uuid;
    try {
        await productsApi.review(p.uuid, 'approve');
        drop(p, `«${p.name}» опубліковано`);
    } catch (e) {
        flash.value = toApiError(e).message;
    } finally {
        busy.value = null;
    }
}

async function openNote(p: Product, action: 'reject' | 'archive') {
    noteFor.value = { uuid: p.uuid, action };
    note.value = '';
    noteError.value = '';
    await nextTick();
    document.getElementById(`pnote-${p.uuid}`)?.querySelector('textarea')?.focus();
}

async function confirmNote(p: Product) {
    if (!noteFor.value) return;
    if (note.value.trim().length < 5) {
        noteError.value = 'Напишіть, що не так (щонайменше 5 символів). Майстер побачить цей коментар.';
        return;
    }
    busy.value = p.uuid;
    try {
        await productsApi.review(p.uuid, noteFor.value.action, note.value.trim());
        const verb = noteFor.value.action === 'reject' ? 'повернуто на правки' : 'знято з продажу';
        noteFor.value = null;
        drop(p, `«${p.name}» ${verb}`);
    } catch (e) {
        const err = toApiError(e);
        noteError.value = err.fields.note ?? err.message;
    } finally {
        busy.value = null;
    }
}
</script>

<template>
    <div class="max-w-[1080px]">
        <h1 class="font-flair m-0 text-[40px] leading-tight font-bold tracking-[-0.015em] text-plum">Перевірка товарів</h1>

        <div role="tablist" aria-label="Статус товару" class="mt-8 flex flex-wrap gap-2">
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
                {{ PRODUCT_STATUS_LABELS[t] }}
            </button>
        </div>

        <p v-if="flash" role="status" class="mt-6 mb-0 rounded-[10px] bg-lavender-50 px-4 py-3 text-[15px] text-plum">{{ flash }}</p>

        <div v-if="loading" class="mt-8 space-y-4" aria-busy="true" aria-label="Завантаження">
            <div v-for="i in 3" :key="i" class="h-32 animate-pulse rounded-card bg-lavender-50" />
        </div>

        <div v-else-if="error" role="alert" class="mt-8 flex flex-col items-start gap-4 rounded-card border border-kalyna/30 bg-kalyna/5 p-6">
            <p class="m-0 text-kalyna">{{ error }}</p>
            <BaseButton variant="secondary" @click="load">Спробувати ще раз</BaseButton>
        </div>

        <p v-else-if="items.length === 0" class="mt-10 text-lg text-ink-muted">
            {{ tab === 'pending_review' ? 'Черга порожня — усі товари перевірено.' : 'Тут поки нічого.' }}
        </p>

        <ul v-else class="mt-8 mb-0 flex list-none flex-col gap-4 p-0">
            <li v-for="p in items" :key="p.uuid" class="rounded-card border border-line p-5">
                <div class="flex flex-wrap gap-5">
                    <ProductCover :uuid="p.uuid" :name="p.name" :src="p.images[0]?.url" class="!w-28 shrink-0" />
                    <div class="min-w-0 flex-[1_1_320px]">
                        <RouterLink :to="{ name: 'product', params: { slug: p.slug } }" class="text-xl font-semibold text-plum">{{ p.name }}</RouterLink>
                        <div class="mt-1 text-[15px] text-ink-muted">
                            {{ p.maker?.display_name }} · {{ p.category?.name ?? 'без категорії' }} · {{ p.is_digital ? 'цифровий' : 'фізичний' }} ·
                            <span class="font-semibold text-plum">{{ formatPrice(p.price.amount_minor, p.price.currency) }}</span>
                        </div>
                        <p class="mt-3 mb-0 text-ink" :class="expanded === p.uuid ? '' : 'line-clamp-2'">{{ p.description }}</p>
                        <button v-if="(p.description?.length ?? 0) > 160" type="button" class="mt-1 text-sm font-semibold text-plum" @click="expanded = expanded === p.uuid ? null : p.uuid">
                            {{ expanded === p.uuid ? 'Згорнути' : 'Читати повністю' }}
                        </button>
                        <div class="mt-2 text-sm text-ink-muted">
                            Фото: {{ p.images.length }}
                            <template v-if="p.is_digital"> · файлів: {{ p.files?.length ?? 0 }}</template>
                            <template v-else> · варіантів: {{ p.variants.length }}</template>
                        </div>
                        <p v-if="p.review_note" class="mt-2 mb-0 text-sm text-ink-muted"><span class="font-semibold">Попередній коментар:</span> {{ p.review_note }}</p>
                    </div>
                    <div class="flex flex-wrap items-start gap-2">
                        <template v-if="tab === 'pending_review'">
                            <BaseButton :loading="busy === p.uuid" @click="approve(p)">Опублікувати</BaseButton>
                            <BaseButton variant="secondary" @click="openNote(p, 'reject')">Повернути на правки</BaseButton>
                        </template>
                        <BaseButton v-else-if="tab === 'published'" variant="secondary" @click="openNote(p, 'archive')">Зняти з продажу</BaseButton>
                    </div>
                </div>

                <form v-if="noteFor?.uuid === p.uuid" :id="`pnote-${p.uuid}`" class="mt-5 flex flex-col gap-3 border-t border-line pt-5" @submit.prevent="confirmNote(p)" @keydown.esc="noteFor = null">
                    <BaseTextarea
                        v-model="note"
                        :label="noteFor.action === 'reject' ? 'Що потрібно виправити' : 'Причина зняття з продажу'"
                        :rows="3"
                        :maxlength="2000"
                        hint="Майстер побачить цей коментар у кабінеті."
                        :error="noteError"
                        required
                    />
                    <div class="flex flex-wrap gap-2">
                        <BaseButton type="submit" :loading="busy === p.uuid">{{ noteFor.action === 'reject' ? 'Повернути на правки' : 'Зняти з продажу' }}</BaseButton>
                        <BaseButton variant="ghost" @click="noteFor = null">Скасувати</BaseButton>
                    </div>
                </form>
            </li>
        </ul>
    </div>
</template>
