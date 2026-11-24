<script setup lang="ts">
import { computed } from 'vue';

/** Перше фото товару або «вишита» заглушка, стабільна для uuid. */
const props = withDefaults(defineProps<{ uuid: string; name: string; src?: string | null; ratio?: 'square' | 'portrait' }>(), {
    src: null,
    ratio: 'square',
});

const COLORS = ['#DBD0F3', '#F0D9AA', '#D4DDC2', '#E2CFC3', '#CFC2EE', '#EFD3A0'];
const PATTERNS = ['ph-knit', 'ph-weave', 'ph-stitch'] as const;
const seed = computed(() => [...props.uuid].reduce((s, ch) => s + ch.charCodeAt(0), 0));
</script>

<template>
    <img
        v-if="src"
        :src="src"
        :alt="name"
        loading="lazy"
        class="w-full rounded-card object-cover"
        :class="ratio === 'square' ? 'aspect-square' : 'aspect-[4/5]'"
    />
    <div
        v-else
        role="img"
        :aria-label="`${name} — фото ще немає`"
        class="w-full rounded-card"
        :class="[PATTERNS[seed % PATTERNS.length], ratio === 'square' ? 'aspect-square' : 'aspect-[4/5]']"
        :style="{ backgroundColor: COLORS[seed % COLORS.length] }"
    />
</template>
