<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppLogo from '@/components/layout/AppLogo.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseInput from '@/components/ui/BaseInput.vue';
import OrnamentBand from '@/components/ornament/OrnamentBand.vue';
import StarOrnament from '@/components/ornament/StarOrnament.vue';
import { useAuthStore } from '@/stores/auth';
import { toApiError, type FieldErrors } from '@/api/http';
import type { RegisterPayload } from '@/types/user';

const props = withDefaults(defineProps<{ mode: 'login' | 'register'; role?: RegisterPayload['role'] }>(), {
    role: 'buyer',
});

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const isLogin = computed(() => props.mode === 'login');

const loginForm = reactive({ email: '', password: '', remember: true });
const registerForm = reactive<RegisterPayload>({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: props.role,
});
watch(() => props.role, (role) => (registerForm.role = role));

const submitting = ref(false);
const errors = ref<FieldErrors>({});
const formError = ref('');
const errorSummary = ref<HTMLElement | null>(null);

function switchTo(mode: 'login' | 'register') {
    errors.value = {};
    formError.value = '';
    void router.replace({ name: mode, query: route.query });
}

async function afterSuccess() {
    // Після входу — у кабінет; нова майстриня одразу в профіль майстерні
    const fallback = !isLogin.value && registerForm.role === 'master' ? '/account/studio' : '/account';
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : fallback;
    await router.push(redirect);
}

async function submit() {
    submitting.value = true;
    errors.value = {};
    formError.value = '';
    try {
        if (isLogin.value) await auth.login(loginForm);
        else await auth.register(registerForm);
        await afterSuccess();
    } catch (e) {
        const err = toApiError(e);
        errors.value = err.fields;
        // Якщо немає помилок по полях — показуємо загальну
        formError.value = Object.keys(err.fields).length ? '' : err.message;
        await nextTick();
        errorSummary.value?.focus();
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="flex min-h-screen flex-wrap">
        <!-- Форма -->
        <main class="flex min-w-0 flex-[1_1_520px] flex-col px-6 pt-8 pb-12 md:px-10">
            <AppLogo class="self-start" />

            <div class="flex flex-1 items-center justify-center pt-10">
                <div class="w-full max-w-[420px]">
                    <h1 class="font-flair m-0 text-[40px] leading-tight font-bold tracking-[-0.015em] text-plum">
                        {{ isLogin ? 'З поверненням' : 'Створіть акаунт' }}
                    </h1>
                    <p class="mt-2.5 mb-0 text-[17px] text-ink-muted">
                        {{ isLogin ? 'Увійдіть, щоб побачити замовлення, курси й обране.' : 'Це займе хвилину. Тип акаунта можна буде змінити пізніше.' }}
                    </p>

                    <div role="tablist" aria-label="Вхід або реєстрація" class="mt-7 grid grid-cols-2 rounded-[14px] bg-lavender-50 p-1">
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="isLogin"
                            class="h-11 rounded-[10px] text-[15px] font-semibold text-plum"
                            :class="isLogin ? 'bg-white shadow-sm' : ''"
                            @click="switchTo('login')"
                        >
                            Вхід
                        </button>
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="!isLogin"
                            class="h-11 rounded-[10px] text-[15px] font-semibold text-plum"
                            :class="!isLogin ? 'bg-white shadow-sm' : ''"
                            @click="switchTo('register')"
                        >
                            Реєстрація
                        </button>
                    </div>

                    <!-- Google OAuth: бекенд уже має googleRedirect/googleCallback, роути закоментовані -->
                    <button
                        type="button"
                        disabled
                        title="Скоро"
                        class="mt-6 flex h-[52px] w-full items-center justify-center gap-3 rounded-control border-[1.5px] border-line-strong bg-white text-base font-semibold text-ink disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        Продовжити з Google
                    </button>

                    <div class="my-6 flex items-center gap-3 text-sm text-ink-muted">
                        <div class="h-px flex-1 bg-line" />
                        або поштою
                        <div class="h-px flex-1 bg-line" />
                    </div>

                    <div
                        v-if="formError"
                        ref="errorSummary"
                        tabindex="-1"
                        role="alert"
                        class="mb-5 rounded-[10px] border border-kalyna/30 bg-kalyna/5 px-3.5 py-3 text-sm text-kalyna"
                    >
                        {{ formError }}
                    </div>

                    <!-- Вхід -->
                    <form v-if="isLogin" class="flex flex-col gap-[18px]" novalidate @submit.prevent="submit">
                        <BaseInput
                            v-model="loginForm.email"
                            label="Електронна пошта"
                            type="email"
                            autocomplete="email"
                            placeholder="you@example.com"
                            required
                            :error="errors.email"
                        />
                        <BaseInput
                            v-model="loginForm.password"
                            label="Пароль"
                            type="password"
                            autocomplete="current-password"
                            required
                            :error="errors.password"
                        >
                            <template #label-action>
                                <RouterLink to="/" class="text-sm text-plum hover:text-violet">Забули пароль?</RouterLink>
                            </template>
                        </BaseInput>
                        <label class="flex items-center gap-2.5 text-[15px] text-ink">
                            <input v-model="loginForm.remember" type="checkbox" class="h-5 w-5 accent-plum" />
                            Запам’ятати мене
                        </label>
                        <BaseButton type="submit" size="lg" block :loading="submitting">
                            {{ submitting ? 'Входимо…' : 'Увійти' }}
                        </BaseButton>
                    </form>

                    <!-- Реєстрація -->
                    <form v-else class="flex flex-col gap-[18px]" novalidate @submit.prevent="submit">
                        <fieldset class="m-0 border-0 p-0">
                            <legend class="mb-2 text-[15px] font-semibold text-ink">Я хочу</legend>
                            <div class="grid grid-cols-2 gap-2.5">
                                <button
                                    v-for="option in [
                                        { id: 'buyer', title: 'Купувати й навчатися', text: 'Товари, курси, майстер-класи' },
                                        { id: 'master', title: 'Відкрити майстерню', text: 'Продавати й навчати' },
                                    ] as const"
                                    :key="option.id"
                                    type="button"
                                    :aria-pressed="registerForm.role === option.id"
                                    class="rounded-control border-2 p-3.5 text-left text-ink"
                                    :class="registerForm.role === option.id ? 'border-plum bg-lavender-50' : 'border-line bg-white hover:border-line-strong'"
                                    @click="registerForm.role = option.id"
                                >
                                    <div class="text-[15px] font-semibold">{{ option.title }}</div>
                                    <div class="mt-1 text-[13px] leading-snug text-ink-muted">{{ option.text }}</div>
                                </button>
                            </div>
                        </fieldset>

                        <p v-if="registerForm.role === 'master'" class="m-0 rounded-[10px] bg-notice-bg px-3.5 py-3 text-sm leading-snug text-notice-ink">
                            Профіль майстра з'явиться в каталозі після перевірки адміністратором. Зазвичай це займає до двох робочих днів.
                        </p>

                        <BaseInput v-model="registerForm.name" label="Ім’я" autocomplete="name" required :error="errors.name" />
                        <BaseInput
                            v-model="registerForm.email"
                            label="Електронна пошта"
                            type="email"
                            autocomplete="email"
                            placeholder="you@example.com"
                            required
                            :error="errors.email"
                        />
                        <BaseInput
                            v-model="registerForm.password"
                            label="Пароль"
                            type="password"
                            autocomplete="new-password"
                            hint="Щонайменше 8 символів"
                            required
                            :error="errors.password"
                        />
                        <BaseInput
                            v-model="registerForm.password_confirmation"
                            label="Повторіть пароль"
                            type="password"
                            autocomplete="new-password"
                            required
                            :error="errors.password_confirmation"
                        />
                        <BaseButton type="submit" size="lg" block :loading="submitting">
                            {{ submitting ? 'Створюємо…' : 'Створити акаунт' }}
                        </BaseButton>
                        <p class="m-0 text-[13px] leading-normal text-ink-muted">
                            Створюючи акаунт, ви погоджуєтеся з <a href="#" class="text-plum">умовами</a> і
                            <a href="#" class="text-plum">політикою конфіденційності</a>.
                        </p>
                    </form>
                </div>
            </div>
        </main>

        <!-- Панель бренду -->
        <aside class="tx-knit relative flex min-h-[360px] flex-[1_1_480px] items-end overflow-hidden bg-lavender-100 p-10 md:p-14">
            <OrnamentBand start="tree" class="!absolute inset-x-0 top-0" />
            <StarOrnament :size="260" class="absolute top-[140px] right-14 hidden md:block" />
            <div class="relative max-w-[440px]">
                <p class="font-flair m-0 text-[34px] leading-tight font-semibold text-plum">
                    Кожна річ тут має автора, якого можна знайти й подякувати.
                </p>
                <p class="mt-4 mb-0 text-base text-ink-soft">Українською та англійською · EUR, USD, UAH</p>
            </div>
        </aside>
    </div>
</template>
