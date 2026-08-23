export type UserRole = 'admin' | 'manager' | 'cashier';

export interface User {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

// Employee = User restricted to non-admin roles, used on the User Management page
export interface Employee {
    id: number;
    name: string;
    email: string;
    role: 'manager' | 'cashier';
    created_at: string;
}