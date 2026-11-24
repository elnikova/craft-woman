<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import AppLogo from './AppLogo.vue';
import OrnamentBand from '@/components/ornament/OrnamentBand.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();
const menuOpen = ref(false);

const nav = [
    { label: 'Майстри', to: { name: 'makers' } },
    { label: 'Товари', to: { name: 'catalog' } },
    { label: 'Курси', to: { path: '/', hash: '#courses' } },
    { label: 'Майстер-класи', to: { path: '/', hash: '#workshops' } },
    { label: 'Спільнота', to: { path: '/', hash: '#community' } },
];

async function logout() {
    await auth.logout();
    await router.push({ name: 'home' });
}
</script>

<template>
    <div class="relative">
        <!-- Рушник як фон меню: приглушений, щоб текст навігації лишався читабельним -->
        <OrnamentBand start="barvinok" class="!absolute inset-x-0 top-1/2 -translate-y-1/2 opacity-25" />
        <header class="relative mx-auto flex max-w-[1280px] flex-wrap items-center gap-6 px-6 py-5 md:px-8">
            <AppLogo />

            <button
                type="button"
                class="ml-auto grid h-11 w-11 place-items-center rounded-full text-plum lg:hidden"
                :aria-expanded="menuOpen"
                aria-controls="main-nav"
                aria-label="Меню"
                @click="menuOpen = !menuOpen"
            >
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M4 7h16M4 12h16M4 17h16" />
                </svg>
            </button>

            <nav
                id="main-nav"
                aria-label="Основна навігація"
                class="w-full flex-col gap-1 text-[15px] font-medium lg:flex lg:w-auto lg:flex-1 lg:flex-row lg:gap-6"
                :class="menuOpen ? 'flex' : 'hidden'"
            >
                <RouterLink
                    v-for="item in nav"
                    :key="item.label"
                    :to="item.to"
                    class="py-2 whitespace-nowrap text-plum no-underline hover:text-violet lg:py-0"
                    @click="menuOpen = false"
                >
                    {{ item.label }}
                </RouterLink>
            </nav>

            <div class="flex w-full items-center gap-2 lg:w-auto" :class="menuOpen ? 'flex' : 'hidden lg:flex'">
                <button
                    type="button"
                    class="h-11 rounded-full border border-lavender-200 bg-white px-3 text-sm text-plum"
                    aria-label="Мова і валюта: українська, EUR"
                >
                    UA · EUR
                </button>
                <RouterLink to="/" aria-label="Обране" class="grid h-11 w-11 place-items-center rounded-full text-plum">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z" />
                    </svg>
                </RouterLink>
                <RouterLink to="/" aria-label="Кошик" class="grid h-11 w-11 place-items-center rounded-full text-plum">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 8h14l-1 12H6L5 8z" /><path d="M9 8V6a3 3 0 0 1 6 0v2" />
                    </svg>
                </RouterLink>

                <template v-if="auth.isAuthenticated">
                    <BaseButton :to="{ name: 'account' }">Кабінет</BaseButton>
                    <BaseButton variant="secondary" @click="logout">Вийти</BaseButton>
                </template>
                <BaseButton v-else :to="{ name: 'login' }">Увійти</BaseButton>
            </div>
        </header>
    </div>
</template>
