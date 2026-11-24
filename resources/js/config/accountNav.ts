import type { RouteLocationRaw } from 'vue-router';
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
    label: string;
    to: RouteLocationRaw;
    icon: IconName;
    /** Розділ ще не реалізовано — показуємо з позначкою «скоро» */
    soon?: boolean;
}

export interface NavSection {
    title?: string;
    items: NavItem[];
}

const soon = (slug: string, label: string, icon: IconName): NavItem => ({
    label,
    icon,
    soon: true,
    to: { name: 'account-soon', params: { section: slug } },
});

const overview: NavItem = { label: 'Огляд', to: { name: 'account' }, icon: 'home' };

const customer: NavSection = {
    title: 'Покупки',
    items: [
        { label: 'Замовлення', to: { name: 'account-orders' }, icon: 'bag' },
        soon('learning', 'Мої курси', 'book'),
        soon('bookings', 'Майстер-класи', 'calendar'),
        soon('favorites', 'Обране', 'heart'),
    ],
};

const maker: NavSection = {
    title: 'Майстерня',
    items: [
        { label: 'Профіль майстерні', to: { name: 'studio' }, icon: 'store' },
        { label: 'Товари', to: { name: 'studio-products' }, icon: 'box' },
        { label: 'Продажі', to: { name: 'studio-sales' }, icon: 'chart' },
        soon('teaching', 'Курси', 'book'),
        soon('workshops', 'Майстер-класи', 'calendar'),
    ],
};

const admin: NavSection = {
    title: 'Адміністрування',
    items: [
        { label: 'Перевірка майстрів', to: { name: 'admin-makers' }, icon: 'check' },
        soon('users', 'Користувачі', 'users'),
        { label: 'Категорії', to: { name: 'admin-categories' }, icon: 'tag' },
        { label: 'Перевірка товарів', to: { name: 'admin-products' }, icon: 'flag' },
        { label: 'Усі замовлення', to: { name: 'admin-orders' }, icon: 'bag' },
    ],
};

const settings: NavSection = { items: [soon('settings', 'Налаштування', 'settings')] };

/** Пункти меню кабінету за роллю. Майстер — це ще й покупець (domain-model.md §4.1). */
export function accountNav(role: Role, hasStudio: boolean): NavSection[] {
    if (role === 'admin') return [{ items: [overview] }, admin, settings];
    if (role === 'master' || hasStudio) return [{ items: [overview] }, maker, customer, settings];

    return [
        { items: [overview] },
        customer,
        { title: 'Продавати', items: [{ label: 'Відкрити майстерню', to: { name: 'studio' }, icon: 'store' }] },
        settings,
    ];
}

export const ROLE_LABELS: Record<Role, string> = {
    admin: 'Адміністратор',
    master: 'Майстер',
    buyer: 'Покупець',
};
