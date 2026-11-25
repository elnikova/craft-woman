<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import AppLogo from './AppLogo.vue';
import LocaleSwitcher from './LocaleSwitcher.vue';
import OrnamentBand from '@/components/ornament/OrnamentBand.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import { useFavoritesStore } from '@/stores/favorites';

const auth = useAuthStore();
const cart = useCartStore();
const favorites = useFavoritesStore();
const router = useRouter();
const menuOpen = ref(false);
const { t } = useI18n();

const nav = computed(() => [
    { label: t('nav.makers'), to: { name: 'makers' } },
    { label: t('nav.products'), to: { name: 'catalog' } },
    { label: t('nav.courses'), to: { name: 'courses' } },
    { label: t('nav.workshops'), to: { name: 'workshops' } },
    { label: t('nav.community'), to: { name: 'home', hash: '#community' } },
]);

async function logout() {
    await auth.logout();
    await router.push({ name: 'home' });
}
</script>

<template>
    <div class="relative">
        <!-- Rushnyk as the menu background, muted so the navigation text stays readable -->
        <OrnamentBand start="barvinok" class="!absolute inset-x-0 top-1/2 -translate-y-1/2 opacity-25" />
        <header class="relative mx-auto flex max-w-[1280px] flex-wrap items-center gap-6 px-6 py-5 md:px-8">
            <AppLogo />

            <button
                type="button"
                class="ml-auto grid h-11 w-11 place-items-center rounded-full text-plum lg:hidden"
                :aria-expanded="menuOpen"
                aria-controls="main-nav"
                :aria-label="t('nav.menu')"
                @click="menuOpen = !menuOpen"
            >
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M4 7h16M4 12h16M4 17h16" />
                </svg>
            </button>

            <nav
                id="main-nav"
                :aria-label="t('nav.main')"
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
                <LocaleSwitcher />
                <RouterLink
                    :to="{ name: 'account-favorites' }"
                    :aria-label="favorites.count ? t('nav.favoritesCount', { n: favorites.count }) : t('nav.favorites')"
                    class="relative grid h-11 w-11 place-items-center rounded-full text-plum"
                >
                    <span
                        v-if="favorites.count"
                        class="absolute top-0.5 right-0 grid h-5 min-w-5 place-items-center rounded-full bg-plum px-1 text-[11px] leading-none font-bold text-white"
                        aria-hidden="true"
                        >{{ favorites.count > 99 ? '99+' : favorites.count }}</span
                    >
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z" />
                    </svg>
                </RouterLink>
                <RouterLink
                    :to="{ name: 'cart' }"
                    :aria-label="cart.count ? t('nav.cartCount', { n: cart.count }) : t('nav.cart')"
                    class="relative grid h-11 w-11 place-items-center rounded-full text-plum"
                >
                    <span
                        v-if="cart.count"
                        class="absolute top-0.5 right-0 grid h-5 min-w-5 place-items-center rounded-full bg-plum px-1 text-[11px] leading-none font-bold text-white"
                        aria-hidden="true"
                        >{{ cart.count > 99 ? '99+' : cart.count }}</span
                    >
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 8h14l-1 12H6L5 8z" /><path d="M9 8V6a3 3 0 0 1 6 0v2" />
                    </svg>
                </RouterLink>

                <template v-if="auth.isAuthenticated">
                    <BaseButton :to="{ name: 'account' }">{{ t('nav.account') }}</BaseButton>
                    <BaseButton variant="secondary" @click="logout">{{ t('nav.logout') }}</BaseButton>
                </template>
                <BaseButton v-else :to="{ name: 'login' }">{{ t('nav.login') }}</BaseButton>
            </div>
        </header>
    </div>
</template>
