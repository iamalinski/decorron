import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface FlashMessages {
    success?: string | null;
    error?: string | null;
    info?: string | null;
}

export interface AppConfig {
    currency: string;
    locale: string;
    registration_enabled: boolean;
}

export interface SharedData {
    name: string;
    auth: Auth;
    app: AppConfig;
    flash: FlashMessages;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
}

/** Отговор на Laravel ResourceCollection с пагинация. */
export interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
    };
}

/** Отговор на ResourceCollection без пагинация. */
export interface Collection<T> {
    data: T[];
}

/** Един ред от списъка „какво пречи на изтриването“. */
export interface DeletionBlocker {
    key: string;
    label: string;
    count: number;
}

/**
 * Полетата за Кошчето.
 *
 * `deleted_at` идва навсякъде; останалите само на екраните с подробности,
 * където има бутон за изтриване върху конкретния запис.
 */
export interface Trashable {
    deleted_at?: string | null;
    can_be_deleted?: boolean;
    deletion_blockers?: DeletionBlocker[];
    can_be_deleted_forever?: boolean;
    restore_blockers?: string[];
}
