import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

declare module 'vue-router' {
    interface RouteMeta {
        title?: string;
        /** Сторінка тільки для гостей (вхід, реєстрація) */
        guestOnly?: boolean;
        /** Потрібна автентифікація */
        requiresAuth?: boolean;
        /** Тільки для адміністратора */
        requiresAdmin?: boolean;
        /** Сторінка сама малює шапку (головна: шапка всередині першого екрана) */
        ownHeader?: boolean;
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
                {
                    path: '',
                    name: 'home',
                    component: () => import('@/pages/HomePage.vue'),
                    meta: { title: 'Речі, зроблені руками', ownHeader: true },
                },
                {
                    path: 'makers',
                    name: 'makers',
                    component: () => import('@/pages/makers/MakersPage.vue'),
                    meta: { title: 'Майстри' },
                },
                {
                    path: 'makers/:slug',
                    name: 'maker',
                    component: () => import('@/pages/makers/MakerPage.vue'),
                    props: true,
                    meta: { title: 'Майстер' },
                },
            ],
        },
        {
            path: '/account',
            component: () => import('@/layouts/AccountLayout.vue'),
            meta: { requiresAuth: true },
            children: [
                {
                    path: '',
                    name: 'account',
                    component: () => import('@/pages/account/AccountHomePage.vue'),
                    meta: { title: 'Кабінет', requiresAuth: true },
                },
                {
                    path: 'studio',
                    name: 'studio',
                    component: () => import('@/pages/studio/StudioProfilePage.vue'),
                    meta: { title: 'Профіль майстерні', requiresAuth: true },
                },
                {
                    path: 'admin/makers',
                    name: 'admin-makers',
                    component: () => import('@/pages/admin/AdminMakersPage.vue'),
                    meta: { title: 'Перевірка майстрів', requiresAuth: true, requiresAdmin: true },
                },
                {
                    path: 'soon/:section',
                    name: 'account-soon',
                    component: () => import('@/pages/account/ComingSoonPage.vue'),
                    props: true,
                    meta: { title: 'Скоро', requiresAuth: true },
                },
            ],
        },
        // Старі адреси
        { path: '/studio', redirect: { name: 'studio' } },
        { path: '/admin/makers', redirect: { name: 'admin-makers' } },
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

    if ((to.meta.requiresAuth || to.meta.requiresAdmin) && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }
    if (to.meta.requiresAdmin && !auth.isAdmin) {
        return { name: 'not-found', params: { pathMatch: to.path.slice(1).split('/') } };
    }
    if (to.meta.guestOnly && auth.isAuthenticated) {
        return { name: 'home' };
    }
    return true;
});

router.afterEach((to) => {
    document.title = to.meta.title ? `${to.meta.title} — Craft Woman` : 'Craft Woman';
});
