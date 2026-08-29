import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href?: string;
    icon?: LucideIcon;
    isActive?: boolean;
    items?: NavItem[];
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    shop: {
        name: string;
        logo_url: string | null;
    };
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export type UserRole = 'admin' | 'manager' | 'cashier';
export type EmployeeStatus = 'working' | 'break' | 'off_duty';

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    role: UserRole;
    status: EmployeeStatus;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

// The employee list/edit/create pages only ever deal with manager/cashier users.
export type Employee = User;

export type BreadcrumbItemType = BreadcrumbItem;

export interface Backup {
    name: string;
    size: number;
    size_human: string;
    date: string;
    type: 'full' | 'pre-restore';
}

export interface BackupResponse {
    backups: Backup[];
}

export interface RestoreResponse {
    success: boolean;
    message?: string;
}