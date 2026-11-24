<script setup lang="ts">
import { computed } from 'vue';

/**
 * Рушникова смуга вишитих хрестиків.
 * Чотири мотиви плавно змінюють один одного (~5 с показ, 2 с перехід).
 * `start` — з якого мотиву почати, щоб сусідні смуги не збігались.
 * При prefers-reduced-motion показується тільки стартовий мотив.
 */
export type OrnamentMotif = 'rhombus' | 'tree' | 'barvinok' | 'mountains';

const props = withDefaults(defineProps<{ start?: OrnamentMotif; animated?: boolean }>(), {
    start: 'rhombus',
    animated: true,
});

const MOTIFS: { id: OrnamentMotif; src: string; width: number }[] = [
    { id: 'rhombus', src: '/images/ornaments/band-rhombus.svg', width: 80 },
    { id: 'tree', src: '/images/ornaments/band-tree.svg', width: 80 },
    { id: 'barvinok', src: '/images/ornaments/band-barvinok.svg', width: 70 },
    { id: 'mountains', src: '/images/ornaments/band-mountains.svg', width: 60 },
];

const CYCLE_SECONDS = 28;
const STEP = CYCLE_SECONDS / MOTIFS.length;

const layers = computed(() => {
    const startIndex = MOTIFS.findIndex((m) => m.id === props.start);
    const ordered = [...MOTIFS.slice(startIndex), ...MOTIFS.slice(0, startIndex)];
    const list = props.animated ? ordered : ordered.slice(0, 1);

    return list.map((motif, i) => ({
        id: motif.id,
        style: {
            backgroundImage: `url('${motif.src}')`,
            backgroundSize: `${motif.width}px 65px`,
            animationDelay: i === 0 ? '0s' : `-${CYCLE_SECONDS - i * STEP}s`,
        },
    }));
});
</script>

<template>
    <div class="relative h-[65px]" :class="{ 'is-animated': animated }" aria-hidden="true">
        <div v-for="layer in layers" :key="layer.id" class="band-layer" :style="layer.style" />
    </div>
</template>

<style scoped>
.band-layer {
    position: absolute;
    inset: 0;
    background-repeat: repeat-x;
    background-position: center top;
}

.is-animated .band-layer {
    animation: orn-fade 28s ease-in-out infinite;
}

@media (prefers-reduced-motion: reduce) {
    .is-animated .band-layer {
        animation: none;
    }
    .is-animated .band-layer:not(:first-child) {
        opacity: 0;
    }
}
</style>
