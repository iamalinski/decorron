import type { NavItem } from '@/types';
import { Calculator, History, LayoutDashboard, Package, Ruler, Settings } from 'lucide-react';

/**
 * Основната навигация. Използва се едновременно от страничното меню
 * (широки екрани) и от долната лента (мобилни устройства).
 */
export const mainNavItems: NavItem[] = [
    { title: 'Табло', url: '/dashboard', icon: LayoutDashboard },
    { title: 'Нова калкулация', url: '/calculations/create', icon: Calculator },
    { title: 'История', url: '/calculations', icon: History },
    { title: 'Материали', url: '/materials', icon: Package },
    { title: 'Мерни единици', url: '/units', icon: Ruler },
    { title: 'Настройки', url: '/settings/pricing', icon: Settings },
];

/** Съкратени етикети за долната лента на телефон. */
export const mobileNavItems: { title: string; url: string; icon: NavItem['icon'] }[] = [
    { title: 'Табло', url: '/dashboard', icon: LayoutDashboard },
    { title: 'Нова', url: '/calculations/create', icon: Calculator },
    { title: 'История', url: '/calculations', icon: History },
    { title: 'Материали', url: '/materials', icon: Package },
    { title: 'Още', url: '/settings/pricing', icon: Settings },
];

/** Дали даден навигационен адрес отговаря на текущия URL. */
export function isActiveUrl(itemUrl: string, currentUrl: string): boolean {
    const current = currentUrl.split('?')[0];

    if (itemUrl === '/calculations') {
        return current === '/calculations' || /^\/calculations\/\d+/.test(current);
    }

    if (itemUrl === '/settings/pricing') {
        return current.startsWith('/settings');
    }

    return current === itemUrl || current.startsWith(`${itemUrl}/`);
}
