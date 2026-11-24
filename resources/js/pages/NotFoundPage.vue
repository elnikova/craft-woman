<script setup lang="ts">
import AppLogo from '@/components/layout/AppLogo.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import OrnamentBand from '@/components/ornament/OrnamentBand.vue';
</script>

<template>
    <div class="tx-linen flex min-h-screen flex-col bg-lavender-100">
        <div class="px-6 pt-8 md:px-10"><AppLogo /></div>
        <main class="flex flex-1 flex-col items-center justify-center gap-6 px-6 text-center">
            <h1 class="font-flair m-0 text-5xl font-bold text-plum">Цю сторінку ще не вишили</h1>
            <p class="m-0 max-w-[480px] text-lg text-ink-soft">Можливо, посилання застаріло або в адресі помилка.</p>
            <BaseButton size="lg" to="/">На головну</BaseButton>
        </main>
        <OrnamentBand start="barvinok" />
    </div>
</template>
