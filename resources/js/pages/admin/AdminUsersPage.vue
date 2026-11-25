<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ROLE_LABELS } from '@/config/accountNav';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseTextarea from '@/components/ui/BaseTextarea.vue';
import MakerStatusBadge from '@/components/makers/MakerStatusBadge.vue';
import { adminUsersApi } from '@/api/account';
import { toApiError } from '@/api/http';
import type { AdminUser, Role, UserStatus } from '@/types/user';
import { formatDate } from '@/utils/orderStatus';

const { t } = useI18n();

const q = ref('');
const role = ref<Role | ''>('');
const status = ref<UserStatus | ''>('');
const users = ref<AdminUser[]>([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(true);
const error = ref('');

/** Action error, shown next to the row it belongs to */
const rowError = ref<{ uuid: string; message: string } | null>(null);
const busy = ref<string | null>(null);

async function load(p = 1) {
    loading.value = true;
    error.value = '';
    try {
        const res = await adminUsersApi.list({ q: q.value.trim() || undefined, role: role.value || undefined, status: status.value || undefined, page: p });
        users.value = p === 1 ? res.data : [...users.value, ...res.data];
        page.value = res.meta.current_page;
        lastPage.value = res.meta.last_page;
        total.value = res.meta.total;
    } catch (e) {
        error.value = toApiError(e).message;
    } finally {
        loading.value = false;
    }
}

function replace(updated: AdminUser) {
    users.value = users.value.map((u) => (u.uuid === updated.uuid ? updated : u));
}

async function act(u: AdminUser, action: () => Promise<AdminUser>) {
    busy.value = u.uuid;
    rowError.value = null;
    try {
        replace(await action());
    } catch (e) {
        rowError.value = { uuid: u.uuid, message: toApiError(e).message };
    } finally {
        busy.value = null;
    }
}

function toggleAdmin(u: AdminUser) {
    const toAdmin = u.role !== 'admin';
    const text = toAdmin
        ? t('adminUsers.makeAdminConfirm', { name: u.name })
        : t('adminUsers.removeAdminConfirm', { name: u.name });
    if (!window.confirm(text)) return;
    act(u, () => adminUsersApi.setRole(u.uuid, toAdmin ? 'admin' : 'buyer'));
}

const unblock = (u: AdminUser) => act(u, () => adminUsersApi.unblock(u.uuid));

// ---------- Block dialog ----------
const dialog = ref<HTMLDialogElement | null>(null);
const target = ref<AdminUser | null>(null);
const reason = ref('');
const reasonError = ref('');
const blocking = ref(false);
let opener: HTMLElement | null = null;

async function openBlock(u: AdminUser, event: Event) {
    opener = event.currentTarget as HTMLElement;
    target.value = u;
    reason.value = '';
    reasonError.value = '';
    dialog.value?.showModal();
    await nextTick();
    dialog.value?.querySelector('textarea')?.focus();
}

function closeBlock() {
    dialog.value?.close();
}

async function confirmBlock() {
    if (!target.value) return;
    blocking.value = true;
    reasonError.value = '';
    try {
        replace(await adminUsersApi.block(target.value.uuid, reason.value.trim()));
        closeBlock();
    } catch (e) {
        const err = toApiError(e);
        reasonError.value = err.fields.reason ?? err.message;
    } finally {
        blocking.value = false;
    }
}

let timer: ReturnType<typeof setTimeout> | undefined;
watch(q, () => {
    clearTimeout(timer);
    timer = setTimeout(() => load(), 300);
});
watch([role, status], () => load());
onMounted(() => load());
</script>

<template>
    <div class="max-w-[1180px]">
        <h1 class="font-flair m-0 text-[40px] leading-tight font-bold tracking-[-0.015em] text-plum">{{ t('adminUsers.title') }}</h1>

        <div class="mt-8 flex flex-wrap gap-3">
            <input
                v-model="q"
                type="search"
                :aria-label="t('adminUsers.search')"
                :placeholder="t('adminUsers.searchPlaceholder')"
                class="h-11 min-w-[260px] flex-1 rounded-full border border-line-strong bg-white px-4 text-[15px] focus:border-plum focus:outline-none"
            />
            <select v-model="role" :aria-label="t('adminUsers.role')" class="h-11 rounded-full border border-line-strong bg-white px-4 text-[15px]">
                <option value="">{{ t('adminUsers.allRoles') }}</option>
                <option v-for="(label, r) in ROLE_LABELS" :key="r" :value="r">{{ label }}</option>
            </select>
            <select v-model="status" :aria-label="t('adminUsers.access')" class="h-11 rounded-full border border-line-strong bg-white px-4 text-[15px]">
                <option value="">{{ t('adminUsers.anyAccess') }}</option>
                <option value="active">{{ t('adminUsers.activeMany') }}</option>
                <option value="blocked">{{ t('adminUsers.blockedMany') }}</option>
            </select>
        </div>

        <div v-if="loading && users.length === 0" class="mt-8 space-y-2" aria-busy="true" :aria-label="t('common.loading')">
            <div v-for="i in 6" :key="i" class="h-16 animate-pulse rounded-[12px] bg-lavender-50" />
        </div>
        <div v-else-if="error" role="alert" class="mt-8 flex flex-col items-start gap-4 rounded-card border border-kalyna/30 bg-kalyna/5 p-6">
            <p class="m-0 text-kalyna">{{ error }}</p>
            <BaseButton variant="secondary" @click="load()">{{ t('common.retry') }}</BaseButton>
        </div>
        <p v-else-if="users.length === 0" class="mt-10 text-lg text-ink-muted">{{ t('adminUsers.empty') }}</p>

        <template v-else>
            <p class="mt-6 mb-2 text-sm text-ink-muted">{{ t('common.found', { n: total }) }}</p>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] border-collapse text-[15px]">
                    <thead>
                        <tr class="border-b border-line-strong text-left text-sm text-ink-muted">
                            <th class="py-2 pr-3 font-semibold">{{ t('adminUsers.colUser') }}</th>
                            <th class="py-2 pr-3 font-semibold">{{ t('adminUsers.colRole') }}</th>
                            <th class="py-2 pr-3 font-semibold">{{ t('adminUsers.colStudio') }}</th>
                            <th class="py-2 pr-3 font-semibold">{{ t('adminUsers.colSince') }}</th>
                            <th class="py-2 pr-3 font-semibold">{{ t('adminUsers.colAccess') }}</th>
                            <th class="py-2 font-semibold"><span class="sr-only">{{ t('adminUsers.colActions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="u in users" :key="u.uuid">
                            <tr class="border-b border-line align-top" :class="u.status === 'blocked' ? 'bg-[#F6F4F8]' : ''">
                                <td class="py-3 pr-3">
                                    <div class="font-semibold text-ink">
                                        {{ u.name }} <span v-if="u.is_you" class="text-sm font-normal text-ink-muted">{{ t('adminUsers.you') }}</span>
                                    </div>
                                    <div class="text-sm text-ink-muted">{{ u.email }}</div>
                                </td>
                                <td class="py-3 pr-3">{{ ROLE_LABELS[u.role] }}</td>
                                <td class="py-3 pr-3">
                                    <template v-if="u.maker">
                                        <RouterLink :to="{ name: 'maker', params: { slug: u.maker.slug } }" class="text-plum">{{ u.maker.display_name }}</RouterLink>
                                        <div class="mt-1"><MakerStatusBadge :status="u.maker.status" /></div>
                                    </template>
                                    <span v-else class="text-ink-muted">—</span>
                                </td>
                                <td class="py-3 pr-3 whitespace-nowrap">{{ formatDate(u.created_at) }}</td>
                                <td class="py-3 pr-3">
                                    <span v-if="u.status === 'active'">{{ t('adminUsers.active') }}</span>
                                    <template v-else>
                                        <span class="font-semibold text-kalyna">{{ t('adminUsers.blocked') }}</span>
                                        <div class="max-w-[260px] text-sm text-ink-muted">{{ formatDate(u.blocked_at) }}: {{ u.blocked_reason }}</div>
                                    </template>
                                </td>
                                <td class="py-3">
                                    <div v-if="!u.is_you" class="flex justify-end gap-2 whitespace-nowrap">
                                        <BaseButton
                                            v-if="u.role !== 'master'"
                                           
                                            variant="ghost"
                                            :disabled="busy === u.uuid || u.status === 'blocked'"
                                            @click="toggleAdmin(u)"
                                        >
                                            {{ u.role === 'admin' ? t('adminUsers.removeAdmin') : t('adminUsers.makeAdmin') }}
                                        </BaseButton>
                                        <BaseButton v-if="u.status === 'active'" variant="secondary" :disabled="busy === u.uuid" @click="openBlock(u, $event)">{{ t('adminUsers.block') }}</BaseButton>
                                        <BaseButton v-else variant="secondary" :loading="busy === u.uuid" @click="unblock(u)">{{ t('adminUsers.unblock') }}</BaseButton>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="rowError?.uuid === u.uuid">
                                <td colspan="6" role="alert" class="pb-3 text-kalyna">{{ rowError.message }}</td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <BaseButton v-if="page < lastPage" variant="secondary" class="mt-6" :loading="loading" @click="load(page + 1)">{{ t('common.showMore') }}</BaseButton>
        </template>

        <dialog
            ref="dialog"
            aria-labelledby="block-title"
            class="m-auto w-[min(520px,calc(100vw-32px))] rounded-card border-0 p-0 backdrop:bg-plum/40"
            @close="opener?.focus()"
        >
            <form v-if="target" class="flex flex-col gap-5 p-6" @submit.prevent="confirmBlock">
                <h2 id="block-title" class="m-0 text-xl font-bold text-plum">{{ t('adminUsers.blockTitle', { name: target.name }) }}</h2>
                <p class="m-0 text-[15px] text-ink">
                    {{ t('adminUsers.blockText') }}
                    <template v-if="target.maker">{{ t('adminUsers.blockMaker') }}</template>
                </p>
                <BaseTextarea v-model="reason" :label="t('adminUsers.reason')" :hint="t('adminUsers.reasonHint')" :rows="3" :error="reasonError" />
                <div class="flex flex-wrap justify-end gap-3">
                    <BaseButton variant="ghost" @click="closeBlock">{{ t('adminUsers.cancel') }}</BaseButton>
                    <BaseButton type="submit" :loading="blocking" :disabled="reason.trim().length < 5">{{ t('adminUsers.block') }}</BaseButton>
                </div>
            </form>
        </dialog>
    </div>
</template>
