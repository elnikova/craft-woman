import axios, { AxiosError } from 'axios';

/**
 * One axios client for the whole app.
 * Same-domain SPA → Sanctum session cookie + XSRF-TOKEN.
 * The API version lives in baseURL.
 */
export const http = axios.create({
    baseURL: '/api/v1',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        // Language of API messages (validation, domain errors). Becomes dynamic with the language switcher.
        'Accept-Language': 'uk',
    },
});

// Blocked account: the server has already ended the session; a full reload clears every page and store
http.interceptors.response.use(undefined, (error) => {
    if (error instanceof AxiosError && error.response?.status === 403 && error.response.data?.code === 'account_blocked') {
        if (!window.location.pathname.startsWith('/login')) window.location.assign('/login?blocked=1');
    }
    return Promise.reject(error);
});

/** Get the CSRF cookie before the first state-changing request (login, register). */
export function ensureCsrfCookie(): Promise<unknown> {
    return axios.get('/sanctum/csrf-cookie', { withCredentials: true });
}

/** Laravel validation errors (422) in a handy shape: { field: 'first message' }. */
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

        // Don't show server errors (5xx) to users: they may contain SQL and paths
        if (status >= 500) {
            return { status, message: 'На сервері сталася помилка. Спробуйте трохи пізніше.', fields };
        }

        return { status, message: body.message ?? 'Щось пішло не так. Спробуйте ще раз.', fields };
    }

    return { status: null, message: 'Щось пішло не так. Спробуйте ще раз.', fields: {} };
}
