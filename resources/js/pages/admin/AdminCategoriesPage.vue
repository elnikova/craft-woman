<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { taxonomyApi } from '@/api/taxonomy';
import { toApiError } from '@/api/http';
import type { Category } from '@/types/taxonomy';
import { plural } from '@/utils/format';

const tree = ref<Category[]>([]);
const loading = ref(true);
const loadError = ref('');
const flash = ref('');
const rowError = ref<{ uuid: string; message: string } | null>(null);

/** Which form is open: new root, new subcategory (parent) or edit */
const editing = ref<{ mode: 'root' } | { mode: 'child'; parent: Category } | { mode: 'edit'; category: Category } | null>(null);
const name = ref('');
const nameError = ref('');
const saving = ref(false);
const confirmDelete = ref<string | null>(null);

async function load() {
    loading.value = true;
    loadError.value = '';
    try {
        tree.value = await taxonomyApi.adminCategories();
    } catch (e) {
        loadError.value = toApiError(e).message;
    } finally {
        loading.value = false;
    }
}

async function open(state: NonNullable<typeof editing.value>) {
    editing.value = state;
    name.value = state.mode === 'edit' ? state.category.name : '';
    nameError.value = '';
    rowError.value = null;
    await nextTick();
    document.getElementById('category-name')?.focus();
}

async function save() {
    if (!editing.value) return;
    if (name.value.trim().length < 2) {
        nameError.value = 'Назва — щонайменше 2 символи';
        return;
    }
    saving.value = true;
    nameError.value = '';
    try {
        const e = editing.value;
        if (e.mode === 'edit') {
            await taxonomyApi.updateCategory(e.category.uuid, { name: name.value.trim() });
            flash.value = `Категорію перейменовано на «${name.value.trim()}»`;
        } else {
            await taxonomyApi.createCategory({ name: name.value.trim(), parent_uuid: e.mode === 'child' ? e.parent.uuid : null });
            flash.value = `Категорію «${name.value.trim()}» додано`;
        }
        editing.value = null;
        await load();
    } catch (err) {
        const apiErr = toApiError(err);
        nameError.value = apiErr.fields.name ?? apiErr.message;
    } finally {
        saving.value = false;
    }
}

async function toggleVisible(c: Category) {
    rowError.value = null;
    const next = !c.is_visible;
    c.is_visible = next; // optimistic
    try {
        await taxonomyApi.updateCategory(c.uuid, { is_visible: next });
        flash.value = next ? `«${c.name}» тепер видно в каталозі` : `«${c.name}» приховано з каталогу`;
    } catch (e) {
        c.is_visible = !next;
        rowError.value = { uuid: c.uuid, message: toApiError(e).message };
    }
}

async function remove(c: Category) {
    rowError.value = null;
    try {
        await taxonomyApi.deleteCategory(c.uuid);
        confirmDelete.value = null;
        flash.value = `Категорію «${c.name}» видалено`;
        await load();
    } catch (e) {
        confirmDelete.value = null;
        rowError.value = { uuid: c.uuid, message: toApiError(e).message };
    }
}

onMounted(load);
</script>

<template>
    <div class="max-w-[960px]">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-flair m-0 text-[40px] leading-tight font-bold tracking-[-0.015em] text-plum">Категорії</h1>
                <p class="mt-2 mb-0 text-ink-muted">Два рівні: категорія й підкатегорія. Приховані не показуються в каталозі.</p>
            </div>
            <BaseButton @click="open({ mode: 'root' })">Додати категорію</BaseButton>
        </div>

        <p v-if="flash" role="status" class="mt-6 mb-0 rounded-[10px] bg-lavender-50 px-4 py-3 text-[15px] text-plum">{{ flash }}</p>

        <!-- Form (new / rename) -->
        <form
            v-if="editing"
            class="mt-6 flex flex-wrap items-start gap-3 rounded-card border border-plum/30 bg-lavender-50 p-5"
            novalidate
            @submit.prevent="save"
            @keydown.esc="editing = null"
        >
            <div class="flex min-w-0 flex-[1_1_320px] flex-col gap-2">
                <label for="category-name" class="text-[15px] font-semibold text-ink">
                    <template v-if="editing.mode === 'root'">Нова категорія</template>
                    <template v-else-if="editing.mode === 'child'">Нова підкатегорія в «{{ editing.parent.name }}»</template>
                    <template v-else>Перейменувати «{{ editing.category.name }}»</template>
                </label>
                <input
                    id="category-name"
                    v-model="name"
                    maxlength="80"
                    :aria-invalid="nameError ? true : undefined"
                    aria-describedby="category-name-error"
                    class="h-[52px] rounded-control border-[1.5px] bg-white px-4 text-base text-ink focus:border-plum focus:outline-none"
                    :class="nameError ? 'border-kalyna' : 'border-line-strong'"
                />
                <p v-if="nameError" id="category-name-error" class="m-0 text-sm font-medium text-kalyna">{{ nameError }}</p>
            </div>
            <div class="flex gap-2 pt-[30px]">
                <BaseButton type="submit" :loading="saving">{{ editing.mode === 'edit' ? 'Зберегти' : 'Додати' }}</BaseButton>
                <BaseButton variant="ghost" @click="editing = null">Скасувати</BaseButton>
            </div>
        </form>

        <div v-if="loading" class="mt-8 space-y-3" aria-busy="true" aria-label="Завантаження">
            <div v-for="i in 5" :key="i" class="h-16 animate-pulse rounded-card bg-lavender-50" />
        </div>

        <div v-else-if="loadError" role="alert" class="mt-8 flex flex-col items-start gap-4 rounded-card border border-kalyna/30 bg-kalyna/5 p-6">
            <p class="m-0 text-kalyna">{{ loadError }}</p>
            <BaseButton variant="secondary" @click="load">Спробувати ще раз</BaseButton>
        </div>

        <p v-else-if="tree.length === 0" class="mt-10 text-lg text-ink-muted">Категорій ще немає. Додайте першу — майстри обиратимуть її для товарів.</p>

        <ul v-else class="mt-8 mb-0 flex list-none flex-col gap-3 p-0">
            <li v-for="root in tree" :key="root.uuid" class="rounded-card border border-line">
                <template v-for="(c, i) in [root, ...(root.children ?? [])]" :key="c.uuid">
                    <div
                        class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3.5"
                        :class="[i > 0 ? 'border-t border-line pl-12' : '', !c.is_visible ? 'bg-lavender-50/60' : '']"
                    >
                        <div class="min-w-0 flex-[1_1_220px]">
                            <span :class="i === 0 ? 'text-lg font-semibold text-plum' : 'text-[16px] text-ink'">{{ c.name }}</span>
                            <span v-if="!c.is_visible" class="ml-2 rounded-full bg-ink/10 px-2 py-0.5 text-[12px] font-semibold text-ink">приховано</span>
                            <div class="text-[13px] text-ink-muted">
                                /{{ c.slug }} · {{ plural(c.usage_count ?? 0, 'products') }}
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input type="checkbox" class="h-5 w-5 accent-plum" :checked="c.is_visible" @change="toggleVisible(c)" />
                            У каталозі
                        </label>

                        <div class="flex flex-wrap gap-1">
                            <BaseButton v-if="i === 0" variant="ghost" @click="open({ mode: 'child', parent: c })">+ Підкатегорія</BaseButton>
                            <BaseButton variant="ghost" @click="open({ mode: 'edit', category: c })">Перейменувати</BaseButton>
                            <template v-if="confirmDelete === c.uuid">
                                <BaseButton variant="secondary" @click="remove(c)">Так, видалити</BaseButton>
                                <BaseButton variant="ghost" @click="confirmDelete = null">Ні</BaseButton>
                            </template>
                            <BaseButton v-else variant="ghost" @click="confirmDelete = c.uuid">Видалити</BaseButton>
                        </div>
                    </div>
                    <p v-if="rowError?.uuid === c.uuid" role="alert" class="m-0 px-5 pb-3 text-sm text-kalyna" :class="i > 0 ? 'pl-12' : ''">
                        {{ rowError.message }}
                    </p>
                </template>
            </li>
        </ul>
    </div>
</template>
