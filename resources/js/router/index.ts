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
                    path: 'catalog',
                    name: 'catalog',
                    component: () => import('@/pages/products/CatalogPage.vue'),
                    meta: { title: 'Каталог' },
                },
                {
                    path: 'products/:slug',
                    name: 'product',
                    component: () => import('@/pages/products/ProductPage.vue'),
                    props: true,
                    meta: { title: 'Товар' },
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
                {
                    path: 'cart',
                    name: 'cart',
                    component: () => import('@/pages/cart/CartPage.vue'),
                    meta: { title: 'Кошик' },
                },
                {
                    path: 'checkout',
                    name: 'checkout',
                    component: () => import('@/pages/cart/CheckoutPage.vue'),
                    meta: { title: 'Оформлення', requiresAuth: true },
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
                    path: 'products',
                    name: 'studio-products',
                    component: () => import('@/pages/studio/MyProductsPage.vue'),
                    meta: { title: 'Товари', requiresAuth: true },
                },
                {
                    path: 'products/new',
                    name: 'studio-product-new',
                    component: () => import('@/pages/studio/ProductEditorPage.vue'),
                    meta: { title: 'Новий товар', requiresAuth: true },
                },
                {
                    path: 'products/:uuid',
                    name: 'studio-product',
                    component: () => import('@/pages/studio/ProductEditorPage.vue'),
                    props: true,
                    meta: { title: 'Товар', requiresAuth: true },
                },
                {
                    path: 'admin/products',
                    name: 'admin-products',
                    component: () => import('@/pages/admin/AdminProductsPage.vue'),
                    meta: { title: 'Перевірка товарів', requiresAuth: true, requiresAdmin: true },
                },
                {
                    path: 'admin/categories',
                    name: 'admin-categories',
                    component: () => import('@/pages/admin/AdminCategoriesPage.vue'),
                    meta: { title: 'Категорії', requiresAuth: true, requiresAdmin: true },
                },
                {
                    path: 'orders',
                    name: 'account-orders',
                    component: () => import('@/pages/account/OrdersPage.vue'),
                    meta: { title: 'Замовлення', requiresAuth: true },
                },
                {
                    path: 'orders/:number',
                    name: 'account-order',
                    component: () => import('@/pages/account/OrderPage.vue'),
                    props: true,
                    meta: { title: 'Замовлення', requiresAuth: true },
                },
                {
                    path: 'sales',
                    name: 'studio-sales',
                    component: () => import('@/pages/studio/SalesPage.vue'),
                    meta: { title: 'Продажі', requiresAuth: true },
                },
                {
                    path: 'admin/orders',
                    name: 'admin-orders',
                    component: () => import('@/pages/admin/AdminOrdersPage.vue'),
                    meta: { title: 'Усі замовлення', requiresAuth: true, requiresAdmin: true },
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
