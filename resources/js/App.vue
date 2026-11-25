<script setup lang="ts">
import { watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';

const auth = useAuthStore();
const cart = useCartStore();

// Кошик підтягуємо після перевірки сесії і щоразу, коли користувач входить або виходить
watch(
    () => [auth.checked, auth.user?.uuid] as const,
    ([checked]) => {
        if (checked) cart.sync(auth.isAuthenticated);
    },
    { immediate: true },
);
</script>

<template>
    <!-- Сесію перевіряє router guard (stores/auth → fetchUser) -->
    <RouterView />
</template>
