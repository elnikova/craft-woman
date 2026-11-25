<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { localePath } from '@/i18n';
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

const { t } = useI18n();
const isLogin = computed(() => props.mode === 'login');
const roleOptions = computed(() => [
    { id: 'buyer' as const, title: t('auth.buyerTitle'), text: t('auth.buyerText') },
    { id: 'master' as const, title: t('auth.makerTitle'), text: t('auth.makerText') },
]);

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
const formError = ref(route.query.blocked === '1' ? t('errors.blocked') : '');
const errorSummary = ref<HTMLElement | null>(null);

function switchTo(mode: 'login' | 'register') {
    errors.value = {};
    formError.value = '';
    void router.replace({ name: mode, query: route.query });
}

async function afterSuccess() {
    // After login go to the account; a new maker goes straight to the studio profile
    const fallback = localePath(!isLogin.value && registerForm.role === 'master' ? '/account/studio' : '/account');
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
        // With no field errors, show the general message
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
        <!-- Form -->
        <main class="flex min-w-0 flex-[1_1_520px] flex-col px-6 pt-8 pb-12 md:px-10">
            <AppLogo class="self-start" />

            <div class="flex flex-1 items-center justify-center pt-10">
                <div class="w-full max-w-[420px]">
                    <h1 class="font-flair m-0 text-[40px] leading-tight font-bold tracking-[-0.015em] text-plum">
                        {{ isLogin ? t('auth.welcomeBack') : t('auth.createAccount') }}
                    </h1>
                    <p class="mt-2.5 mb-0 text-[17px] text-ink-muted">
                        {{ isLogin ? t('auth.loginLead') : t('auth.registerLead') }}
                    </p>

                    <div role="tablist" :aria-label="t('auth.tabs')" class="mt-7 grid grid-cols-2 rounded-[14px] bg-lavender-50 p-1">
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="isLogin"
                            class="h-11 rounded-[10px] text-[15px] font-semibold text-plum"
                            :class="isLogin ? 'bg-white shadow-sm' : ''"
                            @click="switchTo('login')"
                        >{{ t('auth.signInTab') }}</button>
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="!isLogin"
                            class="h-11 rounded-[10px] text-[15px] font-semibold text-plum"
                            :class="!isLogin ? 'bg-white shadow-sm' : ''"
                            @click="switchTo('register')"
                        >{{ t('auth.signUpTab') }}</button>
                    </div>

                    <!-- Google OAuth: the backend has googleRedirect/googleCallback, the routes are commented out -->
                    <button
                        type="button"
                        disabled
                        :title="t('auth.soon')"
                        class="mt-6 flex h-[52px] w-full items-center justify-center gap-3 rounded-control border-[1.5px] border-line-strong bg-white text-base font-semibold text-ink disabled:cursor-not-allowed disabled:opacity-60"
                    >{{ t('auth.google') }}</button>

                    <div class="my-6 flex items-center gap-3 text-sm text-ink-muted">
                        <div class="h-px flex-1 bg-line" />
                        {{ t('auth.orEmail') }}
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

                    <!-- Sign in -->
                    <form v-if="isLogin" class="flex flex-col gap-[18px]" novalidate @submit.prevent="submit">
                        <BaseInput
                            v-model="loginForm.email"
                            :label="t('auth.email')"
                            type="email"
                            autocomplete="email"
                            :placeholder="t('auth.emailPlaceholder')"
                            required
                            :error="errors.email"
                        />
                        <BaseInput
                            v-model="loginForm.password"
                            :label="t('auth.password')"
                            type="password"
                            autocomplete="current-password"
                            required
                            :error="errors.password"
                        >
                            <template #label-action>
                                <RouterLink :to="{ name: 'home' }" class="text-sm text-plum hover:text-violet">{{ t('auth.forgot') }}</RouterLink>
                            </template>
                        </BaseInput>
                        <label class="flex items-center gap-2.5 text-[15px] text-ink">
                            <input v-model="loginForm.remember" type="checkbox" class="h-5 w-5 accent-plum" />
                            {{ t('auth.remember') }}
                        </label>
                        <BaseButton type="submit" size="lg" block :loading="submitting">
                            {{ submitting ? t('auth.signingIn') : t('auth.signIn') }}
                        </BaseButton>
                    </form>

                    <!-- Register -->
                    <form v-else class="flex flex-col gap-[18px]" novalidate @submit.prevent="submit">
                        <fieldset class="m-0 border-0 p-0">
                            <legend class="mb-2 text-[15px] font-semibold text-ink">{{ t('auth.iWant') }}</legend>
                            <div class="grid grid-cols-2 gap-2.5">
                                <button
                                    v-for="option in roleOptions"
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
                            {{ t('auth.makerNote') }}
                        </p>

                        <BaseInput v-model="registerForm.name" :label="t('auth.name')" autocomplete="name" required :error="errors.name" />
                        <BaseInput
                            v-model="registerForm.email"
                            :label="t('auth.email')"
                            type="email"
                            autocomplete="email"
                            :placeholder="t('auth.emailPlaceholder')"
                            required
                            :error="errors.email"
                        />
                        <BaseInput
                            v-model="registerForm.password"
                            :label="t('auth.password')"
                            type="password"
                            autocomplete="new-password"
                            :hint="t('auth.passwordHint')"
                            required
                            :error="errors.password"
                        />
                        <BaseInput
                            v-model="registerForm.password_confirmation"
                            :label="t('auth.passwordRepeat')"
                            type="password"
                            autocomplete="new-password"
                            required
                            :error="errors.password_confirmation"
                        />
                        <BaseButton type="submit" size="lg" block :loading="submitting">
                            {{ submitting ? t('auth.creating') : t('auth.create') }}
                        </BaseButton>
                        <p class="m-0 text-[13px] leading-normal text-ink-muted">
                            <i18n-t keypath="auth.terms" tag="span" scope="global">
                                <template #terms><a href="#" class="text-plum">{{ t('auth.termsLink') }}</a></template>
                                <template #privacy><a href="#" class="text-plum">{{ t('auth.privacyLink') }}</a></template>
                            </i18n-t>
                        </p>
                    </form>
                </div>
            </div>
        </main>

        <!-- Brand panel -->
        <aside class="tx-knit relative flex min-h-[360px] flex-[1_1_480px] items-end overflow-hidden bg-lavender-100 p-10 md:p-14">
            <OrnamentBand start="tree" class="!absolute inset-x-0 top-0" />
            <StarOrnament :size="260" class="absolute top-[140px] right-14 hidden md:block" />
            <div class="relative max-w-[440px]">
                <p class="font-flair m-0 text-[34px] leading-tight font-semibold text-plum">
                    {{ t('auth.brand') }}
                </p>
                <p class="mt-4 mb-0 text-base text-ink-soft">{{ t('auth.brandNote') }}</p>
            </div>
        </aside>
    </div>
</template>
