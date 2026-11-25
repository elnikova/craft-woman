<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

// Remount the page when the language changes, so every page refetches its data in the new language
const locale = computed(() => (route.params.locale === 'en' ? 'en' : 'uk'));
</script>

<template>
    <RouterView :key="locale" />
</template>
