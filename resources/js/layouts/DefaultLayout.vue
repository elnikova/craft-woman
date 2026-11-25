<script setup lang="ts">
import { useRoute } from 'vue-router';
import AppHeader from '@/components/layout/AppHeader.vue';
import AppFooter from '@/components/layout/AppFooter.vue';

const route = useRoute();
</script>

<template>
    <!-- The home page draws the header inside its hero; every other page gets it here -->
    <div v-if="!route.meta.ownHeader" class="tx-linen bg-lavender-100">
        <AppHeader />
    </div>
    <main id="main" class="min-h-screen bg-white">
        <RouterView />
    </main>
    <AppFooter />
</template>
