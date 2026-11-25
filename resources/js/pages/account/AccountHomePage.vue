<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import MakerStatusBadge from '@/components/makers/MakerStatusBadge.vue';
import { makersApi } from '@/api/makers';
import { useAuthStore } from '@/stores/auth';
import type { MakerProfile } from '@/types/maker';

const auth = useAuthStore();

const firstName = computed(() => auth.user?.name.split(/\s+/)[0] ?? '');
const isAdmin = computed(() => auth.user?.role === 'admin');
const isMaker = computed(() => !isAdmin.value && (auth.user?.role === 'master' || auth.hasStudio));

const pendingMakers = ref<number | null>(null);
const myProfile = ref<MakerProfile | null>(null);

onMounted(async () => {
    try {
        if (isAdmin.value) pendingMakers.value = (await makersApi.reviewQueue('pending_review')).meta.total;
        if (isMaker.value) myProfile.value = await makersApi.mine();
    } catch {
        /* картки просто покажуть дефолтний стан */
    }
});
</script>

<template>
    <div class="max-w-[1080px]">
        <h1 class="font-flair m-0 text-[36px] leading-tight font-bold tracking-[-0.015em] text-plum md:text-[44px]">Вітаємо, {{ firstName }}</h1>

        <!-- Адміністратор -->
        <div v-if="isAdmin" class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            <RouterLink :to="{ name: 'admin-makers' }" class="group flex flex-col gap-3 rounded-card border border-line p-6 text-inherit no-underline hover:border-plum">
                <span class="grid h-11 w-11 place-items-center rounded-full bg-lavender-50 text-plum"><AppIcon name="check" /></span>
                <span class="text-[15px] text-ink-muted">Майстри на перевірці</span>
                <span class="font-flair text-5xl font-bold text-plum">{{ pendingMakers ?? '—' }}</span>
                <span class="font-semibold text-plum group-hover:underline">Перейти до черги</span>
            </RouterLink>
            <div class="flex flex-col gap-3 rounded-card border border-dashed border-line-strong p-6 text-ink-muted">
                <span class="grid h-11 w-11 place-items-center rounded-full bg-lavender-50 text-plum"><AppIcon name="users" /></span>
                <span>Користувачі, категорії й модерація з’являться тут наступними.</span>
            </div>
        </div>

        <!-- Майстер -->
        <template v-else-if="isMaker">
            <div class="mt-8 flex flex-wrap items-center justify-between gap-6 rounded-card bg-lavender-50 p-6">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-lg font-semibold text-plum">Профіль майстерні</span>
                        <MakerStatusBadge v-if="myProfile?.status" :status="myProfile.status" />
                    </div>
                    <p class="mt-2 mb-0 text-ink-muted">
                        <template v-if="!myProfile || myProfile.status === 'draft'">Заповніть профіль і надішліть на перевірку, щоб почати продавати.</template>
                        <template v-else-if="myProfile.status === 'pending_review'">Профіль на перевірці. Зазвичай це до двох робочих днів.</template>
                        <template v-else-if="myProfile.status === 'rejected'">Модератор залишив коментар — подивіться, що виправити.</template>
                        <template v-else-if="myProfile.status === 'approved'">Профіль опубліковано. Наступний крок — товари, щойно відкриється каталог.</template>
                        <template v-else>Майстерню призупинено. Деталі — у профілі.</template>
                    </p>
                </div>
                <BaseButton :to="{ name: 'studio' }">Відкрити профіль</BaseButton>
            </div>
            <div class="mt-5 grid gap-5 md:grid-cols-3">
                <div v-for="c in [
                    { icon: 'box', title: 'Товари', text: 'Фізичні й цифрові товари' },
                    { icon: 'book', title: 'Курси', text: 'Модулі, уроки, сертифікати' },
                    { icon: 'calendar', title: 'Майстер-класи', text: 'Сесії онлайн і наживо' },
                ] as const" :key="c.title" class="flex flex-col gap-2 rounded-card border border-dashed border-line-strong p-6">
                    <span class="grid h-11 w-11 place-items-center rounded-full bg-lavender-50 text-plum"><AppIcon :name="c.icon" /></span>
                    <span class="font-semibold text-plum">{{ c.title }}</span>
                    <span class="text-ink-muted">{{ c.text }} — скоро</span>
                </div>
            </div>
        </template>

        <!-- Покупець -->
        <template v-else>
            <div class="mt-8 grid gap-5 md:grid-cols-3">
                <div v-for="c in [
                    { icon: 'bag', title: 'Замовлення', text: 'Тут зʼявляться ваші покупки.' },
                    { icon: 'book', title: 'Мої курси', text: 'Курси й прогрес навчання.' },
                    { icon: 'heart', title: 'Обране', text: 'Речі й майстри, які сподобались.' },
                ] as const" :key="c.title" class="flex flex-col gap-2 rounded-card border border-line p-6">
                    <span class="grid h-11 w-11 place-items-center rounded-full bg-lavender-50 text-plum"><AppIcon :name="c.icon" /></span>
                    <span class="font-semibold text-plum">{{ c.title }}</span>
                    <span class="text-ink-muted">{{ c.text }}</span>
                </div>
            </div>
            <div class="mt-5 flex flex-wrap items-center justify-between gap-6 rounded-card bg-lavender-50 p-6">
                <div>
                    <div class="text-lg font-semibold text-plum">Шукаєте щось особливе?</div>
                    <p class="mt-1 mb-0 text-ink-muted">Познайомтеся з майстрами та їхніми роботами.</p>
                </div>
                <BaseButton :to="{ name: 'makers' }">Дивитися майстрів</BaseButton>
            </div>
        </template>
    </div>
</template>
