<script setup lang="ts">
import { watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import { useFavoritesStore } from '@/stores/favorites';

const auth = useAuthStore();
const cart = useCartStore();
const favorites = useFavoritesStore();

// Кошик підтягуємо після перевірки сесії і щоразу, коли користувач входить або виходить
watch(
    () => [auth.checked, auth.user?.uuid] as const,
    ([checked]) => {
        if (!checked) return;
        cart.sync(auth.isAuthenticated);
        favorites.load(auth.isAuthenticated);
    },
    { immediate: true },
);
</script>

<template>
    <!-- Сесію перевіряє router guard (stores/auth → fetchUser) -->
    <RouterView />
</template>
