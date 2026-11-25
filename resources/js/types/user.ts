import type { MakerStatus } from './maker';

/** Mirrors App\Enums\Role */
export type Role = 'admin' | 'master' | 'buyer';

/** Mirrors Modules\Identity\Domain\UserStatus */
export type UserStatus = 'active' | 'blocked';

/** Mirrors App\Http\Resources\UserResource */
export interface User {
    uuid: string;
    name: string;
    email: string;
    locale: 'uk' | 'en';
    role: Role;
    status: UserStatus;
    email_verified: boolean;
    gender: string | null;
    /** null when the user has no maker profile */
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
    /** buyer by default; admin cannot be chosen at registration */
    role: Extract<Role, 'buyer' | 'master'>;
}

/** Laravel JsonResource wraps data in { data } */
export interface Wrapped<T> {
    data: T;
}

/** Laravel paginate() inside JsonResource::collection */
export interface Paginated<T> {
    data: T[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
}

/** Mirrors Modules\Identity\Presentation\Http\Resources\AdminUserResource */
export interface AdminUser {
    uuid: string;
    name: string;
    email: string;
    email_verified: boolean;
    role: Role;
    status: UserStatus;
    blocked_at: string | null;
    blocked_reason: string | null;
    maker: { uuid: string; display_name: string; slug: string; status: MakerStatus } | null;
    is_you: boolean;
    created_at: string;
}
