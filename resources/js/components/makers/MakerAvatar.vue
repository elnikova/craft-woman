<script setup lang="ts">
import { computed } from 'vue';

/**
 * Фото майстра або «вишита» заглушка: колір і візерунок стабільно
 * виводяться з uuid, тож у кожного майстра своя.
 */
const props = withDefaults(defineProps<{ uuid: string; name: string; src?: string | null; shape?: 'portrait' | 'circle' }>(), {
    src: null,
    shape: 'portrait',
});

const COLORS = ['#CFC2EE', '#EFD3A0', '#CBD5B6', '#DCC5B8', '#DBD0F3', '#F0D9AA'];
const PATTERNS = ['ph-knit', 'ph-weave', 'ph-stitch'] as const;

const seed = computed(() => [...props.uuid].reduce((sum, ch) => sum + ch.charCodeAt(0), 0));
const color = computed(() => COLORS[seed.value % COLORS.length]);
const pattern = computed(() => PATTERNS[seed.value % PATTERNS.length]);
const initials = computed(() =>
    props.name
        .split(/\s+/)
        .slice(0, 2)
        .map((w) => w[0]?.toUpperCase() ?? '')
        .join(''),
);
</script>

<template>
    <img
        v-if="src"
        :src="src"
        :alt="name"
        class="object-cover"
        :class="shape === 'circle' ? 'aspect-square rounded-full' : 'aspect-[4/5] rounded-card'"
    />
    <div
        v-else
        role="img"
        :aria-label="name"
        class="grid place-items-center"
        :class="[pattern, shape === 'circle' ? 'aspect-square rounded-full' : 'aspect-[4/5] rounded-card']"
        :style="{ backgroundColor: color }"
    >
        <span class="font-flair rounded-full bg-white/85 px-3 py-1.5 text-lg font-bold text-plum" aria-hidden="true">{{ initials }}</span>
    </div>
</template>
