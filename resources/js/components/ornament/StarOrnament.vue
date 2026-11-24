<script setup lang="ts">
/** Восьмикутна зірка (Алатир) з вишитих хрестиків. Декоративна. */
withDefaults(defineProps<{ size?: number; variant?: 'deco' | 'logo' }>(), {
    size: 280,
    variant: 'deco',
});
</script>

<template>
    <img
        :src="variant === 'logo' ? '/images/ornaments/star-logo.svg' : '/images/ornaments/star-deco.svg'"
        alt=""
        :width="size"
        :height="size"
        class="pointer-events-none select-none"
    />
</template>
