/** Дзеркало App\Enums\Role */
export type Role = 'admin' | 'master' | 'buyer';

/** Дзеркало App\Http\Resources\UserResource */
export interface User {
    id: number;
    name: string;
    email: string;
    role: Role;
    gender: string | null;
    subscription_id: number | null;
    created_at: string;
    updated_at: string;
}

export interface LoginPayload {
    email: string;
    password: string;
    remember?: boolean;
}

export interface RegisterPayload {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    /** buyer за замовчуванням; admin через реєстрацію недоступний */
    role: Extract<Role, 'buyer' | 'master'>;
}

/** Laravel JsonResource обгортає дані в { data } */
export interface Wrapped<T> {
    data: T;
}
