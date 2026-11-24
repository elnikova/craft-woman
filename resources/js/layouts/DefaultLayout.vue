<script setup lang="ts">
import { useRoute } from 'vue-router';
import AppHeader from '@/components/layout/AppHeader.vue';
import AppFooter from '@/components/layout/AppFooter.vue';

const route = useRoute();
</script>

<template>
    <!-- Головна малює шапку всередині першого екрана; решта сторінок отримує її тут -->
    <div v-if="!route.meta.ownHeader" class="tx-linen bg-lavender-100">
        <AppHeader />
    </div>
    <main id="main" class="min-h-screen bg-white">
        <RouterView />
    </main>
    <AppFooter />
</template>
