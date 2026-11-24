<script setup lang="ts">
import AppFooter from '@/components/layout/AppFooter.vue';
</script>

<template>
    <!-- Шапка живе всередині першого екрана сторінки (лавандовий фон), тому тут лише main + footer -->
    <main id="main" class="min-h-screen bg-white">
        <RouterView />
    </main>
    <AppFooter />
</template>
