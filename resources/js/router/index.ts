import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

declare module 'vue-router' {
    interface RouteMeta {
        title?: string;
        /** Сторінка тільки для гостей (вхід, реєстрація) */
        guestOnly?: boolean;
        /** Потрібна автентифікація */
        requiresAuth?: boolean;
    }
}

export const router = createRouter({
    history: createWebHistory(),
    scrollBehavior(to, _from, saved) {
        if (saved) return saved;
        if (to.hash) return { el: to.hash, behavior: 'smooth' };
        return { top: 0 };
    },
    routes: [
        {
            path: '/',
            component: () => import('@/layouts/DefaultLayout.vue'),
            children: [
                { path: '', name: 'home', component: () => import('@/pages/HomePage.vue'), meta: { title: 'Речі, зроблені руками' } },
            ],
        },
        {
            path: '/login',
            name: 'login',
            component: () => import('@/pages/auth/AuthPage.vue'),
            props: { mode: 'login' },
            meta: { title: 'Вхід', guestOnly: true },
        },
        {
            path: '/register',
            name: 'register',
            component: () => import('@/pages/auth/AuthPage.vue'),
            props: (route) => ({ mode: 'register', role: route.query.role === 'master' ? 'master' : 'buyer' }),
            meta: { title: 'Реєстрація', guestOnly: true },
        },
        {
            path: '/:pathMatch(.*)*',
            name: 'not-found',
            component: () => import('@/pages/NotFoundPage.vue'),
            meta: { title: 'Сторінку не знайдено' },
        },
    ],
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();
    if (!auth.checked) await auth.fetchUser();

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }
    if (to.meta.guestOnly && auth.isAuthenticated) {
        return { name: 'home' };
    }
    return true;
});

router.afterEach((to) => {
    document.title = to.meta.title ? `${to.meta.title} — Craft Woman` : 'Craft Woman';
});
