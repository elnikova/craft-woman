<script setup lang="ts">
import { watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import { useFavoritesStore } from '@/stores/favorites';

const auth = useAuthStore();
const cart = useCartStore();
const favorites = useFavoritesStore();

// Load the cart and favorites after the session check and whenever the user signs in or out
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
    <!-- The router guard checks the session (stores/auth → fetchUser) -->
    <RouterView />
</template>
