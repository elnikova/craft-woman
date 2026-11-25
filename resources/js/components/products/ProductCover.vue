<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';

/** First product photo or an "embroidered" placeholder that is stable per uuid. */
const props = withDefaults(defineProps<{ uuid: string; name: string; src?: string | null; ratio?: 'square' | 'portrait' | 'wide' }>(), {
    src: null,
    ratio: 'square',
});

const COLORS = ['#DBD0F3', '#F0D9AA', '#D4DDC2', '#E2CFC3', '#CFC2EE', '#EFD3A0'];
const PATTERNS = ['ph-knit', 'ph-weave', 'ph-stitch'] as const;
const seed = computed(() => [...props.uuid].reduce((s, ch) => s + ch.charCodeAt(0), 0));

const { t } = useI18n();
const ratioClass = computed(() => ({ square: 'aspect-square', portrait: 'aspect-[4/5]', wide: 'aspect-video' })[props.ratio]);
</script>

<template>
    <img
        v-if="src"
        :src="src"
        :alt="name"
        loading="lazy"
        class="w-full rounded-card object-cover"
        :class="ratioClass"
    />
    <div
        v-else
        role="img"
        :aria-label="t('product.noPhoto', { name })"
        class="w-full rounded-card"
        :class="[PATTERNS[seed % PATTERNS.length], ratioClass]"
        :style="{ backgroundColor: COLORS[seed % COLORS.length] }"
    />
</template>
