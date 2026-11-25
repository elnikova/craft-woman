<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import AppLogo from '@/components/layout/AppLogo.vue';
import OrnamentBand from '@/components/ornament/OrnamentBand.vue';
import StarOrnament from '@/components/ornament/StarOrnament.vue';
import { coursesApi } from '@/api/courses';
import type { CertificateView } from '@/types/course';
import { formatDate } from '@/utils/orderStatus';

const props = defineProps<{ number: string }>();
const { t } = useI18n();

const cert = ref<CertificateView | null>(null);
const loading = ref(true);
const missing = ref(false);
const verifyUrl = computed(() => window.location.href.replace(/\?.*$/, ''));

const print = () => window.print();

onMounted(async () => {
    try {
        cert.value = await coursesApi.verify(props.number);
    } catch {
        missing.value = true;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="min-h-screen bg-lavender-100 px-4 py-8 print:bg-white print:p-0">
        <div class="mx-auto mb-6 flex max-w-[960px] items-center justify-between print:hidden">
            <AppLogo />
            <BaseButton v-if="cert" variant="secondary" @click="print">{{ t('certificate.print') }}</BaseButton>
        </div>

        <div v-if="loading" class="mx-auto aspect-[1.414] max-w-[960px] animate-pulse rounded-card bg-white" :aria-label="t('common.loading')" />

        <div v-else-if="missing || !cert" role="alert" class="mx-auto max-w-[640px] rounded-card bg-white p-8 text-center text-lg text-kalyna">
            {{ t('certificate.notFound') }}
        </div>

        <article
            v-else
            class="tx-linen relative mx-auto flex aspect-[1.414] max-w-[960px] flex-col overflow-hidden rounded-card bg-white text-center shadow-[0_2px_24px_rgba(58,42,102,0.12)] print:rounded-none print:shadow-none"
        >
            <OrnamentBand start="rhombus" :animated="false" />
            <div class="flex flex-1 flex-col items-center justify-center gap-3 px-10 py-6">
                <StarOrnament :size="72" />
                <p class="m-0 text-[13px] font-semibold tracking-[0.14em] text-ink-soft">CRAFT WOMAN</p>
                <h1 class="font-flair m-0 text-[30px] leading-tight font-bold text-plum md:text-[40px]">{{ t('certificate.heading') }}</h1>
                <p class="mt-4 mb-0 text-lg text-ink-soft">{{ t('certificate.awardedTo') }}</p>
                <p class="font-flair m-0 text-[34px] font-bold text-ink md:text-[44px]">{{ cert.holder_name }}</p>
                <p class="m-0 text-lg text-ink-soft">{{ t('certificate.completed') }}</p>
                <p class="m-0 max-w-[680px] text-[24px] font-semibold text-plum">«{{ cert.course_name }}»</p>
                <p class="m-0 text-[15px] text-ink-soft">{{ t('certificate.by', { maker: cert.maker_name }) }}</p>
                <div class="mt-6 flex flex-wrap justify-center gap-x-8 gap-y-1 text-[15px] text-ink">
                    <span>{{ t('certificate.issued', { date: formatDate(cert.issued_at) }) }}</span>
                    <span class="font-semibold">{{ t('certificate.number', { number: cert.number }) }}</span>
                </div>
                <p class="m-0 text-[12px] break-all text-ink-muted">{{ t('certificate.verify', { url: verifyUrl }) }}</p>
            </div>
            <OrnamentBand start="mountains" :animated="false" />
        </article>

        <div v-if="cert?.course_slug" class="mx-auto mt-6 max-w-[960px] text-center print:hidden">
            <RouterLink :to="{ name: 'course', params: { slug: cert.course_slug } }" class="text-plum">{{ t('certificate.toCourse') }}</RouterLink>
        </div>
    </div>
</template>
