<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import AppLogo from '@/components/layout/AppLogo.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import OrnamentBand from '@/components/ornament/OrnamentBand.vue';

const { t } = useI18n();
</script>

<template>
    <div class="tx-linen flex min-h-screen flex-col bg-lavender-100">
        <div class="px-6 pt-8 md:px-10"><AppLogo /></div>
        <main class="flex flex-1 flex-col items-center justify-center gap-6 px-6 text-center">
            <h1 class="font-flair m-0 text-5xl font-bold text-plum">{{ t('notFound.title') }}</h1>
            <p class="m-0 max-w-[480px] text-lg text-ink-soft">{{ t('notFound.text') }}</p>
            <BaseButton size="lg" :to="{ name: 'home' }">{{ t('notFound.home') }}</BaseButton>
        </main>
        <OrnamentBand start="barvinok" />
    </div>
</template>
