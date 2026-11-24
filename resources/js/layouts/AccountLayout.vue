<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppLogo from '@/components/layout/AppLogo.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import OrnamentBand from '@/components/ornament/OrnamentBand.vue';
import { accountNav, ROLE_LABELS } from '@/config/accountNav';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const sections = computed(() => (auth.user ? accountNav(auth.user.role, auth.hasStudio) : []));
const menuOpen = ref(false);

// Закриваємо мобільне меню після переходу
watch(() => route.fullPath, () => (menuOpen.value = false));

function isActive(to: unknown): boolean {
    const target = router.resolve(to as Parameters<typeof router.resolve>[0]);
    if (target.name === 'account') return route.name === 'account';
    // Вкладені сторінки (редактор товару) підсвічують свій розділ
    return route.path === target.path || route.path.startsWith(`${target.path}/`);
}

async function logout() {
    await auth.logout();
    await router.push({ name: 'home' });
}
</script>

<template>
    <div class="min-h-screen bg-white lg:grid lg:grid-cols-[288px_minmax(0,1fr)]">
        <!-- Бічне меню -->
        <aside
            id="account-nav"
            class="tx-linen fixed inset-y-0 left-0 z-40 flex w-72 flex-col overflow-y-auto bg-lavender-100 transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
            :class="menuOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full'"
            aria-label="Меню кабінету"
        >
            <div class="px-6 pt-6 pb-4">
                <AppLogo />
            </div>
            <OrnamentBand start="tree" class="opacity-40" />

            <div class="px-6 pt-5 pb-2">
                <div class="truncate text-[15px] font-semibold text-ink">{{ auth.user?.name }}</div>
                <div class="mt-0.5 text-sm text-ink-soft">{{ auth.user ? ROLE_LABELS[auth.user.role] : '' }}</div>
            </div>

            <nav class="flex flex-1 flex-col gap-5 px-3 py-4">
                <div v-for="(section, si) in sections" :key="si">
                    <div v-if="section.title" class="px-3 pb-2 text-[13px] font-semibold text-ink-soft">{{ section.title }}</div>
                    <ul class="m-0 flex list-none flex-col gap-0.5 p-0">
                        <li v-for="item in section.items" :key="item.label">
                            <RouterLink
                                :to="item.to"
                                class="flex h-11 items-center gap-3 rounded-control px-3 text-[15px] no-underline transition-colors"
                                :class="isActive(item.to) ? 'bg-plum font-semibold text-white' : 'text-plum hover:bg-white/70'"
                                :aria-current="isActive(item.to) ? 'page' : undefined"
                            >
                                <AppIcon :name="item.icon" />
                                <span class="flex-1">{{ item.label }}</span>
                                <span
                                    v-if="item.soon"
                                    class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                    :class="isActive(item.to) ? 'bg-white/20 text-white' : 'bg-white/80 text-ink-soft'"
                                >
                                    скоро
                                </span>
                            </RouterLink>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="border-t border-lavender-200 px-3 py-4">
                <RouterLink to="/" class="flex h-11 items-center gap-3 rounded-control px-3 text-[15px] text-plum no-underline hover:bg-white/70">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                        <path d="M15 18l-6-6 6-6" />
                    </svg>
                    На сайт
                </RouterLink>
            </div>
        </aside>

        <!-- Затемнення під мобільним меню -->
        <div v-if="menuOpen" class="fixed inset-0 z-30 bg-plum/30 lg:hidden" aria-hidden="true" @click="menuOpen = false" />

        <!-- Робоча область -->
        <div class="flex min-w-0 flex-col">
            <header class="sticky top-0 z-20 flex h-[72px] items-center gap-4 border-b border-line bg-white/95 px-6 backdrop-blur md:px-10">
                <button
                    type="button"
                    class="-ml-2 grid h-11 w-11 place-items-center rounded-full text-plum lg:hidden"
                    :aria-expanded="menuOpen"
                    aria-controls="account-nav"
                    aria-label="Меню кабінету"
                    @click="menuOpen = !menuOpen"
                >
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <path d="M4 7h16M4 12h16M4 17h16" />
                    </svg>
                </button>
                <RouterLink :to="{ name: 'account' }" class="min-w-0 flex-1 truncate text-lg font-semibold text-plum no-underline">Кабінет</RouterLink>
                <button
                    type="button"
                    class="h-11 rounded-full border-[1.5px] border-plum px-5 text-[15px] font-semibold text-plum hover:bg-lavender-50"
                    @click="logout"
                >
                    Вийти
                </button>
            </header>

            <main id="main" class="flex-1 px-6 py-10 md:px-10">
                <RouterView />
            </main>
        </div>
    </div>
</template>
