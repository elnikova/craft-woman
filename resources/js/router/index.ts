import { createRouter, createWebHistory } from 'vue-router';
import { DEFAULT_LOCALE, applyLocale, currentLocale, isLocale, t } from '@/i18n';
import { useAuthStore } from '@/stores/auth';

declare module 'vue-router' {
    interface RouteMeta {
        /** Guest-only page (login, register) */
        guestOnly?: boolean;
        /** Requires authentication */
        requiresAuth?: boolean;
        /** Administrators only */
        requiresAdmin?: boolean;
        /** The page draws its own header (home: header inside the hero) */
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
            // Ukrainian has no prefix; English lives under /en (domain-rules.md §42)
            path: '/:locale(en)?',
            component: () => import('@/layouts/LocaleRoot.vue'),
            children: [
                {
                    path: '',
                    component: () => import('@/layouts/DefaultLayout.vue'),
                    children: [
                        {
                            path: '',
                            name: 'home',
                            component: () => import('@/pages/HomePage.vue'),
                            meta: { ownHeader: true },
                        },
                        {
                            path: 'catalog',
                            name: 'catalog',
                            component: () => import('@/pages/products/CatalogPage.vue'),
                        },
                        {
                            path: 'products/:slug',
                            name: 'product',
                            component: () => import('@/pages/products/ProductPage.vue'),
                            props: true,
                        },
                        {
                            path: 'makers',
                            name: 'makers',
                            component: () => import('@/pages/makers/MakersPage.vue'),
                        },
                        {
                            path: 'makers/:slug',
                            name: 'maker',
                            component: () => import('@/pages/makers/MakerPage.vue'),
                            props: true,
                        },
                        {
                            path: 'courses',
                            name: 'courses',
                            component: () => import('@/pages/courses/CoursesPage.vue'),
                        },
                        {
                            path: 'courses/:slug',
                            name: 'course',
                            component: () => import('@/pages/courses/CoursePage.vue'),
                            props: true,
                        },
                        {
                            path: 'courses/:slug/learn/:lesson',
                            name: 'course-lesson',
                            component: () => import('@/pages/courses/LessonPage.vue'),
                            props: true,
                        },
                        {
                            path: 'cart',
                            name: 'cart',
                            component: () => import('@/pages/cart/CartPage.vue'),
                        },
                        {
                            path: 'checkout',
                            name: 'checkout',
                            component: () => import('@/pages/cart/CheckoutPage.vue'),
                            meta: { requiresAuth: true },
                        },
                    ],
                },
                {
                    path: 'account',
                    component: () => import('@/layouts/AccountLayout.vue'),
                    meta: { requiresAuth: true },
                    children: [
                        {
                            path: '',
                            name: 'account',
                            component: () => import('@/pages/account/AccountHomePage.vue'),
                            meta: { requiresAuth: true },
                        },
                        {
                            path: 'studio',
                            name: 'studio',
                            component: () => import('@/pages/studio/StudioProfilePage.vue'),
                            meta: { requiresAuth: true },
                        },
                        {
                            path: 'admin/makers',
                            name: 'admin-makers',
                            component: () => import('@/pages/admin/AdminMakersPage.vue'),
                            meta: { requiresAuth: true, requiresAdmin: true },
                        },
                        {
                            path: 'products',
                            name: 'studio-products',
                            component: () => import('@/pages/studio/MyProductsPage.vue'),
                            meta: { requiresAuth: true },
                        },
                        {
                            path: 'products/new',
                            name: 'studio-product-new',
                            component: () => import('@/pages/studio/ProductEditorPage.vue'),
                            meta: { requiresAuth: true },
                        },
                        {
                            path: 'products/:uuid',
                            name: 'studio-product',
                            component: () => import('@/pages/studio/ProductEditorPage.vue'),
                            props: true,
                            meta: { requiresAuth: true },
                        },
                        {
                            path: 'admin/products',
                            name: 'admin-products',
                            component: () => import('@/pages/admin/AdminProductsPage.vue'),
                            meta: { requiresAuth: true, requiresAdmin: true },
                        },
                        {
                            path: 'admin/categories',
                            name: 'admin-categories',
                            component: () => import('@/pages/admin/AdminCategoriesPage.vue'),
                            meta: { requiresAuth: true, requiresAdmin: true },
                        },
                        {
                            path: 'orders',
                            name: 'account-orders',
                            component: () => import('@/pages/account/OrdersPage.vue'),
                            meta: { requiresAuth: true },
                        },
                        {
                            path: 'orders/:number',
                            name: 'account-order',
                            component: () => import('@/pages/account/OrderPage.vue'),
                            props: true,
                            meta: { requiresAuth: true },
                        },
                        {
                            path: 'settings',
                            name: 'account-settings',
                            component: () => import('@/pages/account/SettingsPage.vue'),
                            meta: { requiresAuth: true },
                        },
                        {
                            path: 'admin/users',
                            name: 'admin-users',
                            component: () => import('@/pages/admin/AdminUsersPage.vue'),
                            meta: { requiresAuth: true, requiresAdmin: true },
                        },
                        {
                            path: 'favorites',
                            name: 'account-favorites',
                            component: () => import('@/pages/account/FavoritesPage.vue'),
                            meta: { requiresAuth: true },
                        },
                        {
                            path: 'sales',
                            name: 'studio-sales',
                            component: () => import('@/pages/studio/SalesPage.vue'),
                            meta: { requiresAuth: true },
                        },
                        {
                            path: 'admin/orders',
                            name: 'admin-orders',
                            component: () => import('@/pages/admin/AdminOrdersPage.vue'),
                            meta: { requiresAuth: true, requiresAdmin: true },
                        },
                        {
                            path: 'learning',
                            name: 'account-learning',
                            component: () => import('@/pages/account/LearningPage.vue'),
                            meta: { requiresAuth: true },
                        },
                        {
                            path: 'courses',
                            name: 'studio-courses',
                            component: () => import('@/pages/studio/MyCoursesPage.vue'),
                            meta: { requiresAuth: true },
                        },
                        {
                            path: 'courses/new',
                            name: 'studio-course-new',
                            component: () => import('@/pages/studio/CourseEditorPage.vue'),
                            meta: { requiresAuth: true },
                        },
                        {
                            path: 'courses/:uuid',
                            name: 'studio-course',
                            component: () => import('@/pages/studio/CourseEditorPage.vue'),
                            props: true,
                            meta: { requiresAuth: true },
                        },
                        {
                            path: 'admin/courses',
                            name: 'admin-courses',
                            component: () => import('@/pages/admin/AdminCoursesPage.vue'),
                            meta: { requiresAuth: true, requiresAdmin: true },
                        },
                        {
                            path: 'soon/:section',
                            name: 'account-soon',
                            component: () => import('@/pages/account/ComingSoonPage.vue'),
                            props: true,
                            meta: { requiresAuth: true },
                        },
                    ],
                },
                // Old URLs
                { path: 'studio', redirect: { name: 'studio' } },
                { path: 'admin/makers', redirect: { name: 'admin-makers' } },
                {
                    // Standalone printable page, no site chrome
                    path: 'certificates/:number',
                    name: 'certificate',
                    component: () => import('@/pages/courses/CertificatePage.vue'),
                    props: true,
                },
                {
                    path: 'login',
                    name: 'login',
                    component: () => import('@/pages/auth/AuthPage.vue'),
                    props: { mode: 'login' },
                    meta: { guestOnly: true },
                },
                {
                    path: 'register',
                    name: 'register',
                    component: () => import('@/pages/auth/AuthPage.vue'),
                    props: (route) => ({ mode: 'register', role: route.query.role === 'master' ? 'master' : 'buyer' }),
                    meta: { guestOnly: true },
                },
                {
                    path: ':pathMatch(.*)*',
                    name: 'not-found',
                    component: () => import('@/pages/NotFoundPage.vue'),
                },
            ],
        },
    ],
});

let firstNavigation = true;

router.beforeEach(async (to) => {
    const auth = useAuthStore();
    if (!auth.checked) await auth.fetchUser();

    const locale = isLocale(to.params.locale) ? to.params.locale : DEFAULT_LOCALE;
    // Signed-in users who chose English land on the English site when they arrive without a prefix
    if (firstNavigation) {
        firstNavigation = false;
        if (auth.user?.locale === 'en' && locale !== 'en') {
            return { name: to.name ?? 'home', params: { ...to.params, locale: 'en' }, query: to.query, hash: to.hash };
        }
    }
    // Always on the first navigation too, so <html lang> matches the URL from the start
    if (locale !== currentLocale() || document.documentElement.lang !== locale) applyLocale(locale);

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
    const key = `title.${String(to.name ?? '')}`;
    const title = to.name ? t(key) : '';
    document.title = title && title !== key ? `${title} — Craft Woman` : 'Craft Woman';
});
