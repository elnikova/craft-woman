<script setup lang="ts">
import StarOrnament from '@/components/ornament/StarOrnament.vue';
</script>

<template>
    <RouterLink to="/" class="font-flair flex items-center gap-2.5 text-2xl font-bold tracking-tight text-plum no-underline">
        <StarOrnament variant="logo" :size="30" />
        Craft Woman
    </RouterLink>
</template>
