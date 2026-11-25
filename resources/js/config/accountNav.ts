import type { RouteLocationRaw } from 'vue-router';
import { labelMap } from '@/i18n';
import type { Role } from '@/types/user';

export type IconName =
    | 'home'
    | 'bag'
    | 'book'
    | 'calendar'
    | 'heart'
    | 'settings'
    | 'store'
    | 'box'
    | 'chart'
    | 'check'
    | 'users'
    | 'tag'
    | 'flag';

export interface NavItem {
    /** Message key */
    label: string;
    to: RouteLocationRaw;
    icon: IconName;
    /** Section not built yet: shown with a "скоро" (soon) badge */
    soon?: boolean;
}

export interface NavSection {
    /** Message key */
    title?: string;
    items: NavItem[];
}

const soon = (slug: string, label: string, icon: IconName): NavItem => ({
    label,
    icon,
    soon: true,
    to: { name: 'account-soon', params: { section: slug } },
});

const overview: NavItem = { label: 'accountNav.overview', to: { name: 'account' }, icon: 'home' };

const customer: NavSection = {
    title: 'accountNav.purchases',
    items: [
        { label: 'accountNav.orders', to: { name: 'account-orders' }, icon: 'bag' },
        soon('learning', 'accountNav.learning', 'book'),
        soon('bookings', 'accountNav.bookings', 'calendar'),
        { label: 'accountNav.favorites', to: { name: 'account-favorites' }, icon: 'heart' },
    ],
};

const maker: NavSection = {
    title: 'accountNav.studio',
    items: [
        { label: 'accountNav.studioProfile', to: { name: 'studio' }, icon: 'store' },
        { label: 'accountNav.products', to: { name: 'studio-products' }, icon: 'box' },
        { label: 'accountNav.sales', to: { name: 'studio-sales' }, icon: 'chart' },
        soon('teaching', 'accountNav.teaching', 'book'),
        soon('workshops', 'accountNav.workshops', 'calendar'),
    ],
};

const admin: NavSection = {
    title: 'accountNav.admin',
    items: [
        { label: 'accountNav.adminMakers', to: { name: 'admin-makers' }, icon: 'check' },
        { label: 'accountNav.users', to: { name: 'admin-users' }, icon: 'users' },
        { label: 'accountNav.categories', to: { name: 'admin-categories' }, icon: 'tag' },
        { label: 'accountNav.adminProducts', to: { name: 'admin-products' }, icon: 'flag' },
        { label: 'accountNav.allOrders', to: { name: 'admin-orders' }, icon: 'bag' },
    ],
};

const settings: NavSection = { items: [{ label: 'accountNav.settings', to: { name: 'account-settings' }, icon: 'settings' }] };

/** Account menu items by role. A maker is also a buyer (domain-model.md §4.1). */
export function accountNav(role: Role, hasStudio: boolean): NavSection[] {
    if (role === 'admin') return [{ items: [overview] }, admin, settings];
    if (role === 'master' || hasStudio) return [{ items: [overview] }, maker, customer, settings];

    return [
        { items: [overview] },
        customer,
        { title: 'accountNav.sell', items: [{ label: 'accountNav.openStudio', to: { name: 'studio' }, icon: 'store' }] },
        settings,
    ];
}

export const ROLE_LABELS = labelMap<Role>('role', ['admin', 'master', 'buyer']);
