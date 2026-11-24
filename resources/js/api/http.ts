import axios, { AxiosError } from 'axios';

/**
 * Один axios-клієнт для всього застосунку.
 * SPA на тому ж домені → сесійна cookie Sanctum + XSRF-TOKEN.
 * Коли API переїде на /api/v1, змінюється тільки baseURL.
 */
export const http = axios.create({
    baseURL: '/api',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

/** Отримати CSRF-cookie перед першим запитом, що змінює стан (login, register). */
export function ensureCsrfCookie(): Promise<unknown> {
    return axios.get('/sanctum/csrf-cookie', { withCredentials: true });
}

/** Помилки валідації Laravel (422) у зручному вигляді: { field: 'перше повідомлення' }. */
export type FieldErrors = Record<string, string>;

export interface ApiError {
    status: number | null;
    message: string;
    fields: FieldErrors;
}

interface LaravelErrorBody {
    message?: string;
    errors?: Record<string, string[]>;
}

export function toApiError(error: unknown): ApiError {
    if (error instanceof AxiosError) {
        const status = error.response?.status ?? null;
        const body = (error.response?.data ?? {}) as LaravelErrorBody;
        const fields: FieldErrors = {};

        for (const [field, messages] of Object.entries(body.errors ?? {})) {
            if (messages[0]) fields[field] = messages[0];
        }

        if (status === null) {
            return { status, message: 'Немає з’єднання із сервером. Перевірте інтернет і спробуйте ще раз.', fields };
        }

        // Помилки сервера (5xx) не показуємо користувачу: там можуть бути SQL і шляхи
        if (status >= 500) {
            return { status, message: 'На сервері сталася помилка. Спробуйте трохи пізніше.', fields };
        }

        return { status, message: body.message ?? 'Щось пішло не так. Спробуйте ще раз.', fields };
    }

    return { status: null, message: 'Щось пішло не так. Спробуйте ще раз.', fields: {} };
}
