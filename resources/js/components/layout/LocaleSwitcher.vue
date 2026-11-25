<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import { accountApi } from '@/api/account';
import { type Locale, currentLocale } from '@/i18n';
import { useAuthStore } from '@/stores/auth';

const { t } = useI18n();
const route = useRoute();
const auth = useAuthStore();

const other = computed<Locale>(() => (currentLocale() === 'uk' ? 'en' : 'uk'));

/** Same page in the other language: Ukrainian drops the /en prefix */
const target = computed(() => ({
    name: route.name ?? 'home',
    params: { ...route.params, locale: other.value === 'en' ? 'en' : '' },
    query: route.query,
    hash: route.hash,
}));

/** Signed-in users keep the choice for next time; a failed save doesn't block switching */
function remember() {
    if (auth.user && auth.user.locale !== other.value) {
        accountApi
            .update({ locale: other.value })
            .then((user) => auth.setUser(user))
            .catch(() => undefined);
    }
}
</script>

<template>
    <RouterLink
        :to="target"
        :hreflang="other"
        :lang="other"
        :aria-label="t('nav.switchTo', { lang: t(`lang.${other}`) })"
        class="grid h-11 min-w-11 place-items-center rounded-full border border-lavender-200 bg-white px-3 text-sm font-semibold text-plum no-underline hover:border-plum"
        @click="remember"
    >
        {{ t(`lang.short.${other}`) }}
    </RouterLink>
</template>
