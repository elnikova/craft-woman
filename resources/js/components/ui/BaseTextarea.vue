<script setup lang="ts">
import { computed, useId } from 'vue';

const props = defineProps<{
    label: string;
    rows?: number;
    maxlength?: number;
    placeholder?: string;
    hint?: string;
    error?: string;
    required?: boolean;
    disabled?: boolean;
}>();

const model = defineModel<string | null>({ required: true });

const id = useId();
const hintId = `${id}-hint`;
const errorId = `${id}-error`;
const length = computed(() => model.value?.length ?? 0);

const describedBy = computed(
    () => [props.error ? errorId : null, props.hint || props.maxlength ? hintId : null].filter(Boolean).join(' ') || undefined,
);
</script>

<template>
    <div class="flex flex-col gap-2">
        <label :for="id" class="text-[15px] font-semibold text-ink">{{ label }}</label>
        <textarea
            :id="id"
            v-model="model"
            :rows="rows ?? 4"
            :maxlength="maxlength"
            :placeholder="placeholder"
            :required="required"
            :disabled="disabled"
            :aria-invalid="error ? true : undefined"
            :aria-describedby="describedBy"
            class="w-full resize-y rounded-control border-[1.5px] bg-white px-4 py-3 text-base leading-relaxed text-ink placeholder:text-ink-muted/70 focus:border-plum focus:outline-none disabled:bg-lavender-50 disabled:text-ink-muted"
            :class="error ? 'border-kalyna' : 'border-line-strong'"
        />
        <p v-if="error" :id="errorId" class="m-0 text-sm font-medium text-kalyna">{{ error }}</p>
        <p v-if="hint || maxlength" :id="hintId" class="m-0 flex justify-between gap-4 text-[13px] text-ink-muted">
            <span>{{ hint }}</span>
            <span v-if="maxlength" aria-live="polite">{{ length }} / {{ maxlength }}</span>
        </p>
    </div>
</template>
