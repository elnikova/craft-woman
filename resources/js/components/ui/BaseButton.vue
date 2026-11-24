<script setup lang="ts">
import { computed } from 'vue';
import type { RouteLocationRaw } from 'vue-router';

const props = withDefaults(
    defineProps<{
        variant?: 'primary' | 'secondary' | 'ghost';
        size?: 'md' | 'lg';
        type?: 'button' | 'submit';
        to?: RouteLocationRaw;
        loading?: boolean;
        disabled?: boolean;
        block?: boolean;
    }>(),
    { variant: 'primary', size: 'md', type: 'button', loading: false, disabled: false, block: false },
);

const classes = computed(() => [
    'inline-flex items-center justify-center gap-2 font-semibold no-underline transition-colors',
    'disabled:cursor-not-allowed disabled:opacity-60',
    props.size === 'lg' ? 'h-[52px] px-7 text-[17px]' : 'h-11 px-5 text-[15px]',
    props.block ? 'w-full rounded-control' : 'rounded-full',
    {
        primary: 'bg-plum text-white hover:bg-violet',
        secondary: 'border-[1.5px] border-plum bg-white text-plum hover:bg-lavender-50',
        ghost: 'bg-transparent text-plum hover:bg-lavender-50',
    }[props.variant],
]);
</script>

<template>
    <RouterLink v-if="to" :to="to" :class="classes"><slot /></RouterLink>
    <button v-else :type="type" :class="classes" :disabled="disabled || loading" :aria-busy="loading || undefined">
        <svg v-if="loading" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="40 20" />
        </svg>
        <slot />
    </button>
</template>
