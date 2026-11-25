<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { LOCALES, type Locale } from '@/i18n';

/** "Which language do you write in?" for maker and admin content (domain-rules.md §42). */
const model = defineModel<Locale>({ required: true });
defineProps<{ disabled?: boolean }>();

const { t } = useI18n();
const id = `content-locale-${Math.random().toString(36).slice(2, 8)}`;
</script>

<template>
    <div class="flex flex-col gap-2">
        <label :for="id" class="text-[15px] font-semibold text-ink">{{ t('content.writeIn') }}</label>
        <select
            :id="id"
            v-model="model"
            :disabled="disabled"
            :aria-describedby="`${id}-hint`"
            class="h-[52px] max-w-[320px] rounded-control border-[1.5px] border-line-strong bg-white px-4 text-base text-ink focus:border-plum focus:outline-none disabled:bg-lavender-50"
        >
            <option v-for="l in LOCALES" :key="l" :value="l" :lang="l">{{ t(`lang.${l}`) }}</option>
        </select>
        <p :id="`${id}-hint`" class="m-0 text-[13px] text-ink-muted">{{ t('content.writeInHint') }}</p>
    </div>
</template>
