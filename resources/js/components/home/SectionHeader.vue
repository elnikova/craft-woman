<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router';

defineProps<{ title: string; lead?: string; linkLabel?: string; linkTo?: RouteLocationRaw; id?: string }>();
</script>

<template>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 :id="id" class="font-flair m-0 text-[32px] leading-tight font-bold tracking-[-0.015em] text-plum md:text-[40px]">
                {{ title }}
            </h2>
            <p v-if="lead" class="mt-3 mb-0 max-w-[560px] text-lg text-ink-muted">{{ lead }}</p>
        </div>
        <slot name="aside">
            <RouterLink v-if="linkLabel && linkTo" :to="linkTo" class="font-semibold text-plum hover:text-violet">
                {{ linkLabel }}
            </RouterLink>
        </slot>
    </div>
</template>
