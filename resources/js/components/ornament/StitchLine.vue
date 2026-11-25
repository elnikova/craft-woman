<script setup lang="ts">
/** Dashed "stitch" line running through the hero. Decorative. */
defineProps<{ paths: string[]; viewBox: string }>();
</script>

<template>
    <svg
        aria-hidden="true"
        :viewBox="viewBox"
        preserveAspectRatio="none"
        class="pointer-events-none absolute inset-0 h-full w-full"
    >
        <path
            v-for="(d, i) in paths"
            :key="i"
            :d="d"
            class="stroke-lavender-300"
            stroke-width="3"
            stroke-dasharray="16 12"
            stroke-linecap="round"
            fill="none"
        />
    </svg>
</template>
