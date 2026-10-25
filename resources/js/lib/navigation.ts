import type { LucideIcon } from 'lucide-react';
import {
    Building2,
    Calculator,
    CalendarDays,
    ClipboardList,
    FileText,
    History,
    KeyRound,
    LayoutDashboard,
    ListChecks,
    Package,
    Palette,
    Ruler,
    Settings,
    Target,
    UserCog,
    Users,
} from 'lucide-react';

export interface NavChild {
    title: string;
    /** Съкратен етикет за тесни екрани. По подразбиране се ползва title. */
    shortTitle?: string;
    url: string;
    icon: LucideIcon;
    /**
     * Готова ли е страницата. Неготовите подопции НЕ се показват в менюто —
     * така менюто никога не води до празен или фиктивен екран.
     */
    ready?: boolean;
}

export interface NavSection {
    /** Машинно име — ползва се за ключ и за тестове. */
    key: 'dashboard' | 'crm' | 'pricing';
    title: string;
    shortTitle: string;
    /** Къде води самата секция. */
    url: string;
    icon: LucideIcon;
    /** Префикси на адреси, които принадлежат на секцията. */
    match: string[];
    items: NavChild[];
}

/**
 * Трите основни секции на приложението.
 *
 * Подопция се добавя тук чак когато страницата ѝ е реално реализирана —
 * менюто не трябва да води до празни или фиктивни екрани.
 */
export const navSections: NavSection[] = [
    {
        key: 'dashboard',
        title: 'Табло',
        shortTitle: 'Табло',
        url: '/dashboard',
        icon: LayoutDashboard,
        match: ['/dashboard'],
        items: [],
    },
    {
        key: 'crm',
        title: 'CRM',
        shortTitle: 'CRM',
        url: '/crm',
        icon: Users,
        match: ['/crm'],
        items: [
            { title: 'Табло', url: '/crm', icon: LayoutDashboard },
            { title: 'Клиенти', url: '/crm/customers', icon: Users },
            { title: 'Запитвания', url: '/crm/opportunities', icon: Target },
            { title: 'Оферти', url: '/crm/quotes', icon: FileText },
            { title: 'Поръчки', url: '/crm/orders', icon: ClipboardList },
            { title: 'Задачи', url: '/crm/tasks', icon: ListChecks },
            { title: 'Календар', url: '/crm/calendar', icon: CalendarDays },
        ],
    },
    {
        key: 'pricing',
        title: 'Ценообразуване',
        shortTitle: 'Цени',
        url: '/calculations/create',
        icon: Calculator,
        match: ['/calculations', '/materials', '/units', '/settings/pricing'],
        items: [
            { title: 'Калкулатор', url: '/calculations/create', icon: Calculator },
            { title: 'История', url: '/calculations', icon: History },
            { title: 'Материали', url: '/materials', icon: Package },
            { title: 'Мерни единици', shortTitle: 'Единици', url: '/units', icon: Ruler },
            { title: 'Настройки', url: '/settings/pricing', icon: Settings },
        ],
    },
];

/** Настройки на профила — извън трите основни секции. */
export const accountNavItems: NavChild[] = [
    { title: 'Данни на бизнеса', url: '/settings/company', icon: Building2 },
    { title: 'Профил', url: '/settings/profile', icon: UserCog },
    { title: 'Парола', url: '/settings/password', icon: KeyRound },
    { title: 'Външен вид', url: '/settings/appearance', icon: Palette },
];

/** Подопциите на секцията, чиито страници са реално готови. */
export function readyItems(section: NavSection): NavChild[] {
    return section.items.filter((item) => item.ready !== false);
}

/** Секциите, които имат поне една готова страница. */
export function readySections(): NavSection[] {
    return navSections.filter((section) => section.items.length === 0 || readyItems(section).length > 0);
}

/**
 * Къде води самата секция. Ако нейната начална страница още не е готова,
 * води към първата готова подопция, вместо към празен екран.
 */
export function sectionUrl(section: NavSection): string {
    const items = readyItems(section);

    if (items.length === 0 || items.some((item) => item.url === section.url)) {
        return section.url;
    }

    return items[0].url;
}

/** Пътят без query string. */
export function normalizeUrl(currentUrl: string): string {
    return currentUrl.split('?')[0].split('#')[0] || '/';
}

/** Коя основна секция отговаря на текущия адрес. */
export function activeSection(currentUrl: string): NavSection | undefined {
    const current = normalizeUrl(currentUrl);

    return navSections.find((section) => section.match.some((prefix) => current === prefix || current.startsWith(`${prefix}/`)));
}

/**
 * Дали дадена подопция е активна.
 *
 * „/crm“ и „/calculations/create“ съвпадат точно, защото иначе биха се
 * активирали и за всички вложени адреси на секцията.
 */
export function isActiveUrl(itemUrl: string, currentUrl: string): boolean {
    const current = normalizeUrl(currentUrl);

    if (itemUrl === '/crm' || itemUrl === '/calculations/create') {
        return current === itemUrl;
    }

    if (itemUrl === '/calculations') {
        return current === '/calculations' || /^\/calculations\/\d+/.test(current);
    }

    return current === itemUrl || current.startsWith(`${itemUrl}/`);
}

/** Дали секцията съдържа текущия адрес. */
export function isActiveSection(section: NavSection, currentUrl: string): boolean {
    return activeSection(currentUrl)?.key === section.key;
}
