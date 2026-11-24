<script setup lang="ts">
import { computed, useId } from 'vue';

const props = defineProps<{
    label: string;
    type?: 'text' | 'email' | 'password' | 'search';
    autocomplete?: string;
    placeholder?: string;
    hint?: string;
    error?: string;
    required?: boolean;
    disabled?: boolean;
    maxlength?: number;
}>();

const model = defineModel<string | null>({ required: true });

const id = useId();
const hintId = `${id}-hint`;
const errorId = `${id}-error`;

const describedBy = computed(
    () => [props.error ? errorId : null, props.hint ? hintId : null].filter(Boolean).join(' ') || undefined,
);
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex items-baseline justify-between gap-4">
            <label :for="id" class="text-[15px] font-semibold text-ink">{{ label }}</label>
            <slot name="label-action" />
        </div>
        <input
            :id="id"
            v-model="model"
            :type="type ?? 'text'"
            :autocomplete="autocomplete"
            :placeholder="placeholder"
            :required="required"
            :disabled="disabled"
            :maxlength="maxlength"
            :aria-invalid="error ? true : undefined"
            :aria-describedby="describedBy"
            class="h-[52px] w-full rounded-control border-[1.5px] bg-white px-4 text-base text-ink placeholder:text-ink-muted/70 focus:border-plum focus:outline-none disabled:bg-lavender-50 disabled:text-ink-muted"
            :class="error ? 'border-kalyna' : 'border-line-strong'"
        />
        <p v-if="error" :id="errorId" class="text-sm font-medium text-kalyna">{{ error }}</p>
        <p v-if="hint" :id="hintId" class="text-[13px] text-ink-muted">{{ hint }}</p>
    </div>
</template>
