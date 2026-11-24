import type { MakerStatus } from './maker';

/** Дзеркало App\Enums\Role */
export type Role = 'admin' | 'master' | 'buyer';

/** Дзеркало App\Http\Resources\UserResource */
export interface User {
    uuid: string;
    name: string;
    email: string;
    role: Role;
    gender: string | null;
    /** null — у користувача немає профілю майстра */
    maker_status: MakerStatus | null;
    subscription_uuid: string | null;
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

/** Laravel paginate() у JsonResource::collection */
export interface Paginated<T> {
    data: T[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
}
