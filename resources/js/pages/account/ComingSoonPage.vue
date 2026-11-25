<script setup lang="ts">
import { computed } from 'vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import StarOrnament from '@/components/ornament/StarOrnament.vue';

const props = defineProps<{ section: string }>();

const TITLES: Record<string, string> = {
    orders: 'Замовлення',
    learning: 'Мої курси',
    bookings: 'Майстер-класи',
    favorites: 'Обране',
    settings: 'Налаштування',
    products: 'Товари',
    teaching: 'Курси',
    workshops: 'Майстер-класи',
    sales: 'Продажі',
    users: 'Користувачі',
    categories: 'Категорії',
    moderation: 'Модерація',
    'all-orders': 'Усі замовлення',
};

const title = computed(() => TITLES[props.section] ?? 'Розділ');
</script>

<template>
    <div class="flex max-w-[640px] flex-col items-start gap-5">
        <StarOrnament :size="96" />
        <h1 class="font-flair m-0 text-[36px] leading-tight font-bold text-plum">{{ title }}</h1>
        <p class="m-0 text-lg text-ink-muted">Цей розділ ще вишиваємо. Він зʼявиться разом із наступними модулями платформи.</p>
        <BaseButton variant="secondary" :to="{ name: 'account' }">До огляду</BaseButton>
    </div>
</template>
