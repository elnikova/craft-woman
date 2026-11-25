<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseInput from '@/components/ui/BaseInput.vue';
import { accountApi } from '@/api/account';
import { toApiError, type FieldErrors } from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import type { Role } from '@/types/user';
import { MAKER_STATUS_LABELS } from '@/utils/makerStatus';

const auth = useAuthStore();

const ROLE_LABELS: Record<Role, string> = { buyer: 'Покупець', master: 'Майстер', admin: 'Адміністратор' };

// ---------- Profile ----------
const profile = reactive({ name: auth.user?.name ?? '', email: auth.user?.email ?? '', current_password: '' });
const profileErrors = ref<FieldErrors>({});
const profileError = ref('');
const profileSaved = ref('');
const savingProfile = ref(false);

const emailChanged = computed(() => profile.email.trim().toLowerCase() !== (auth.user?.email ?? '').toLowerCase());
const profileDirty = computed(() => profile.name.trim() !== auth.user?.name || emailChanged.value);

async function saveProfile() {
    savingProfile.value = true;
    profileErrors.value = {};
    profileError.value = '';
    profileSaved.value = '';
    try {
        const user = await accountApi.update({
            name: profile.name.trim(),
            email: profile.email.trim(),
            ...(emailChanged.value ? { current_password: profile.current_password } : {}),
        });
        auth.setUser(user);
        Object.assign(profile, { name: user.name, email: user.email, current_password: '' });
        profileSaved.value = 'Зміни збережено';
    } catch (e) {
        const err = toApiError(e);
        profileErrors.value = err.fields;
        profileError.value = Object.keys(err.fields).length ? '' : err.message;
    } finally {
        savingProfile.value = false;
    }
}

// ---------- Password ----------
const pwd = reactive({ current_password: '', password: '', password_confirmation: '' });
const pwdErrors = ref<FieldErrors>({});
const pwdError = ref('');
const pwdSaved = ref('');
const savingPwd = ref(false);

async function savePassword() {
    pwdErrors.value = {};
    pwdError.value = '';
    pwdSaved.value = '';
    if (pwd.password !== pwd.password_confirmation) {
        pwdErrors.value = { password_confirmation: 'Паролі не збігаються.' };
        return;
    }
    savingPwd.value = true;
    try {
        pwdSaved.value = await accountApi.changePassword({ ...pwd });
        Object.assign(pwd, { current_password: '', password: '', password_confirmation: '' });
    } catch (e) {
        const err = toApiError(e);
        pwdErrors.value = err.fields;
        pwdError.value = Object.keys(err.fields).length ? '' : err.message;
    } finally {
        savingPwd.value = false;
    }
}
</script>

<template>
    <div class="max-w-[720px]">
        <h1 class="font-flair m-0 text-[40px] leading-tight font-bold tracking-[-0.015em] text-plum">Налаштування</h1>

        <section class="mt-8 rounded-card border border-line bg-white p-6" aria-labelledby="profile-title">
            <h2 id="profile-title" class="m-0 text-xl font-bold text-plum">Профіль</h2>
            <form class="mt-5 flex flex-col gap-5" novalidate @submit.prevent="saveProfile">
                <BaseInput v-model="profile.name" label="Ім’я" autocomplete="name" required :error="profileErrors.name" />
                <BaseInput
                    v-model="profile.email"
                    type="email"
                    label="Електронна пошта"
                    autocomplete="email"
                    required
                    :hint="auth.user?.email_verified || emailChanged ? undefined : 'Адресу ще не підтверджено.'"
                    :error="profileErrors.email"
                />
                <BaseInput
                    v-if="emailChanged"
                    v-model="profile.current_password"
                    type="password"
                    label="Поточний пароль"
                    autocomplete="current-password"
                    hint="Пошта — це ваш логін, тому зміну підтверджуємо паролем."
                    required
                    :error="profileErrors.current_password"
                />
                <div v-if="profileError" role="alert" class="rounded-[10px] border border-kalyna/30 bg-kalyna/5 px-4 py-3 text-kalyna">{{ profileError }}</div>
                <div class="flex flex-wrap items-center gap-4">
                    <BaseButton type="submit" :loading="savingProfile" :disabled="!profileDirty">Зберегти</BaseButton>
                    <span v-if="profileSaved" role="status" class="text-[15px] text-ink-muted">{{ profileSaved }}</span>
                </div>
            </form>
        </section>

        <section class="mt-6 rounded-card border border-line bg-white p-6" aria-labelledby="password-title">
            <h2 id="password-title" class="m-0 text-xl font-bold text-plum">Пароль</h2>
            <form class="mt-5 flex flex-col gap-5" novalidate @submit.prevent="savePassword">
                <BaseInput
                    v-model="pwd.current_password"
                    type="password"
                    label="Поточний пароль"
                    autocomplete="current-password"
                    required
                    :error="pwdErrors.current_password"
                />
                <BaseInput
                    v-model="pwd.password"
                    type="password"
                    label="Новий пароль"
                    autocomplete="new-password"
                    hint="Щонайменше 8 символів."
                    required
                    :error="pwdErrors.password"
                />
                <BaseInput
                    v-model="pwd.password_confirmation"
                    type="password"
                    label="Повторіть новий пароль"
                    autocomplete="new-password"
                    required
                    :error="pwdErrors.password_confirmation"
                />
                <div v-if="pwdError" role="alert" class="rounded-[10px] border border-kalyna/30 bg-kalyna/5 px-4 py-3 text-kalyna">{{ pwdError }}</div>
                <div class="flex flex-wrap items-center gap-4">
                    <BaseButton type="submit" :loading="savingPwd" :disabled="!pwd.current_password || !pwd.password">Змінити пароль</BaseButton>
                    <span v-if="pwdSaved" role="status" class="text-[15px] text-ink-muted">{{ pwdSaved }}</span>
                </div>
            </form>
        </section>

        <section class="mt-6 rounded-card bg-lavender-50 p-6" aria-labelledby="account-title">
            <h2 id="account-title" class="m-0 text-xl font-bold text-plum">Акаунт</h2>
            <dl class="mt-4 mb-0 grid grid-cols-[160px_1fr] gap-y-2 text-[15px]">
                <dt class="text-ink-muted">Роль</dt>
                <dd class="m-0">{{ auth.user ? ROLE_LABELS[auth.user.role] : '' }}</dd>
                <template v-if="auth.user?.maker_status">
                    <dt class="text-ink-muted">Майстерня</dt>
                    <dd class="m-0">
                        {{ MAKER_STATUS_LABELS[auth.user.maker_status] }} ·
                        <RouterLink :to="{ name: 'studio' }" class="text-plum">профіль майстерні</RouterLink>
                    </dd>
                </template>
            </dl>
            <p v-if="!auth.user?.maker_status && auth.user?.role === 'buyer'" class="mt-4 mb-0 text-[15px]">
                Хочете продавати свої роботи?
                <RouterLink :to="{ name: 'studio' }" class="font-semibold text-plum">Відкрийте майстерню</RouterLink>
            </p>
        </section>
    </div>
</template>
