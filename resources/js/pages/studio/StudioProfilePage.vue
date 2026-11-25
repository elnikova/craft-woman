<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { onBeforeRouteLeave } from 'vue-router';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseInput from '@/components/ui/BaseInput.vue';
import BaseTextarea from '@/components/ui/BaseTextarea.vue';
import MakerStatusBadge from '@/components/makers/MakerStatusBadge.vue';
import { makersApi } from '@/api/makers';
import { toApiError, type FieldErrors } from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import type { MakerProfile, MakerProfilePayload } from '@/types/maker';
import type { Currency } from '@/types/product';
import { inputToMinor, minorToInput } from '@/utils/format';
import { countries } from '@/utils/orderStatus';
import ContentLanguage from '@/components/ui/ContentLanguage.vue';
import type { Locale } from '@/i18n';

const auth = useAuthStore();
const { t } = useI18n();

const profile = ref<MakerProfile | null>(null);
const loading = ref(true);
const loadError = ref('');

const form = reactive<MakerProfilePayload>({
    display_name: auth.user?.name ?? '',
    bio: '',
    story: '',
    avatar_url: '',
    country_code: 'UA',
    city: '',
    address: '',
    follow_enabled: true,
});
/** Source language of bio/story and the optional version in the other language */
const content = reactive({ locale: 'uk' as Locale, bio: '', story: '' });
const otherLocale = computed<Locale>(() => (content.locale === 'uk' ? 'en' : 'uk'));

/** Delivery and payment: prices are strings in the inputs and minor units for the server */
const shipping = reactive({
    cod_enabled: false,
    delivery_currency: 'EUR' as Currency,
    domestic: '0',
    ships_abroad: false,
    international: '',
});
const snapshot = () => JSON.stringify([form, shipping, content]);
const saved = ref(snapshot());
const dirty = computed(() => snapshot() !== saved.value);

const saving = ref(false);
const submitting = ref(false);
const errors = ref<FieldErrors>({});
const formError = ref('');
const notice = ref('');

const status = computed(() => profile.value?.status ?? null);
const editable = computed(() => profile.value === null || profile.value.is_editable === true);
const canSubmit = computed(() => status.value === 'draft' || status.value === 'rejected');

function fill(p: MakerProfile) {
    profile.value = p;
    Object.assign(form, {
        display_name: p.display_name,
        bio: p.bio ?? '',
        story: p.story ?? '',
        avatar_url: p.avatar_url ?? '',
        country_code: p.country_code ?? 'UA',
        city: p.city ?? '',
        address: p.address ?? '',
        follow_enabled: p.follow_enabled,
    });
    const locale: Locale = p.content_locale ?? 'uk';
    const other = p.translations?.[locale === 'uk' ? 'en' : 'uk'];
    Object.assign(content, { locale, bio: other?.bio ?? '', story: other?.story ?? '' });
    if (p.checkout) {
        Object.assign(shipping, {
            cod_enabled: p.checkout.cod_enabled,
            delivery_currency: p.checkout.delivery_currency,
            domestic: minorToInput(p.checkout.delivery_domestic_minor),
            ships_abroad: p.checkout.delivery_international_minor !== null,
            international: minorToInput(p.checkout.delivery_international_minor),
        });
    }
    saved.value = snapshot();
    auth.setMakerStatus(p.status ?? null);
}

/** Send empty strings as null so blank values aren't stored */
function payload(): MakerProfilePayload {
    const clean = (v: string | null) => (v && v.trim() ? v.trim() : null);
    return {
        display_name: form.display_name.trim(),
        bio: clean(form.bio),
        story: clean(form.story),
        avatar_url: clean(form.avatar_url),
        country_code: clean(form.country_code),
        city: clean(form.city),
        address: clean(form.address),
        follow_enabled: form.follow_enabled,
        cod_enabled: shipping.cod_enabled,
        delivery_currency: shipping.delivery_currency,
        delivery_domestic_minor: inputToMinor(shipping.domestic) ?? 0,
        delivery_international_minor: shipping.ships_abroad ? (inputToMinor(shipping.international) ?? 0) : null,
        content_locale: content.locale,
        translations: { [otherLocale.value]: { bio: clean(content.bio), story: clean(content.story) } },
    };
}

/** Check prices before sending: NaN from inputToMinor means an invalid format */
function shippingErrors(): FieldErrors {
    const e: FieldErrors = {};
    if (Number.isNaN(inputToMinor(shipping.domestic))) e.delivery_domestic_minor = t('studio.enterAmount');
    if (shipping.ships_abroad && Number.isNaN(inputToMinor(shipping.international)))
        e.delivery_international_minor = t('studio.enterAmountAbroad');
    return e;
}

async function load() {
    loading.value = true;
    loadError.value = '';
    try {
        const p = await makersApi.mine();
        if (p) fill(p);
    } catch (e) {
        loadError.value = toApiError(e).message;
    } finally {
        loading.value = false;
    }
}

async function save(): Promise<boolean> {
    saving.value = true;
    errors.value = {};
    formError.value = '';
    notice.value = '';
    const local = shippingErrors();
    if (Object.keys(local).length) {
        errors.value = local;
        formError.value = t('studio.checkFields');
        saving.value = false;
        return false;
    }
    try {
        fill(await makersApi.saveMine(payload()));
        notice.value = t('studio.saved');
        return true;
    } catch (e) {
        const err = toApiError(e);
        errors.value = err.fields;
        formError.value = Object.keys(err.fields).length ? t('studio.checkFields') : err.message;
        return false;
    } finally {
        saving.value = false;
    }
}

async function submitForReview() {
    submitting.value = true;
    try {
        // Save pending edits first, then submit
        if (dirty.value || profile.value === null) {
            if (!(await save())) return;
        }
        fill(await makersApi.submitMine());
        notice.value = t('studio.submitted');
    } catch (e) {
        formError.value = toApiError(e).message;
    } finally {
        submitting.value = false;
    }
}

// Don't lose unsaved changes
function beforeUnload(e: BeforeUnloadEvent) {
    if (dirty.value) e.preventDefault();
}
onMounted(() => {
    void load();
    window.addEventListener('beforeunload', beforeUnload);
});
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));
onBeforeRouteLeave(() => (dirty.value ? window.confirm(t('studio.leaveConfirm')) : true));
</script>

<template>
    <div class="max-w-[880px]">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="font-flair m-0 text-[40px] leading-tight font-bold tracking-[-0.015em] text-plum">{{ t('studio.title') }}</h1>
            <MakerStatusBadge v-if="status" :status="status" />
        </div>

        <div v-if="loading" class="mt-8 animate-pulse space-y-4" aria-busy="true" :aria-label="t('common.loading')">
            <div class="h-24 rounded-card bg-lavender-50" />
            <div class="h-12 rounded-control bg-lavender-50" />
            <div class="h-12 rounded-control bg-lavender-50" />
        </div>

        <div v-else-if="loadError" role="alert" class="mt-8 flex flex-col items-start gap-4 rounded-card border border-kalyna/30 bg-kalyna/5 p-6">
            <p class="m-0 text-kalyna">{{ loadError }}</p>
            <BaseButton variant="secondary" @click="load">{{ t('common.retry') }}</BaseButton>
        </div>

        <template v-else>
            <!-- What is happening with the profile and what to do next -->
            <div class="mt-6 rounded-card p-5" :class="status === 'rejected' || status === 'suspended' ? 'bg-kalyna/5' : 'bg-lavender-50'">
                <template v-if="status === null">
                    <p class="m-0 font-semibold text-plum">{{ t('studio.newTitle') }}</p>
                    <p class="mt-1 mb-0 text-ink-muted">{{ t('studio.newText') }}</p>
                </template>
                <template v-else-if="status === 'draft'">
                    <p class="m-0 font-semibold text-plum">{{ t('studio.draftTitle') }}</p>
                    <p class="mt-1 mb-0 text-ink-muted">{{ t('studio.draftText') }}</p>
                </template>
                <template v-else-if="status === 'pending_review'">
                    <p class="m-0 font-semibold text-plum">{{ t('studio.pendingTitle') }}</p>
                    <p class="mt-1 mb-0 text-ink-muted">{{ t('studio.pendingText') }}</p>
                </template>
                <template v-else-if="status === 'approved'">
                    <p class="m-0 font-semibold text-plum">{{ t('studio.approvedTitle') }}</p>
                    <p class="mt-1 mb-0 text-ink-muted">
                        {{ t('studio.approvedText') }}
                        <RouterLink v-if="profile" :to="{ name: 'maker', params: { slug: profile.slug } }" class="font-semibold text-plum">{{ t('studio.openPublic') }}</RouterLink>
                    </p>
                </template>
                <template v-else-if="status === 'rejected'">
                    <p class="m-0 font-semibold text-kalyna">{{ t('studio.rejectedTitle') }}</p>
                    <p v-if="profile?.review_note" class="mt-2 mb-0 text-ink"><span class="font-semibold">{{ t('studio.moderatorNote') }}</span> {{ profile.review_note }}</p>
                    <p class="mt-2 mb-0 text-ink-muted">{{ t('studio.rejectedText') }}</p>
                </template>
                <template v-else-if="status === 'suspended'">
                    <p class="m-0 font-semibold text-kalyna">{{ t('studio.suspendedTitle') }}</p>
                    <p v-if="profile?.review_note" class="mt-2 mb-0 text-ink"><span class="font-semibold">{{ t('studio.reason') }}</span> {{ profile.review_note }}</p>
                    <p class="mt-2 mb-0 text-ink-muted">{{ t('studio.suspendedText') }}</p>
                </template>
            </div>

            <form class="mt-8 flex flex-col gap-6" novalidate @submit.prevent="save">
                <fieldset :disabled="!editable" class="m-0 flex flex-col gap-6 border-0 p-0">
                    <BaseInput
                        v-model="form.display_name"
                        :label="t('studio.displayName')"
                        :maxlength="120"
                        required
                        :disabled="!editable"
                        :error="errors.display_name"
                    />
                    <BaseTextarea
                        v-model="form.bio"
                        :label="t('studio.bio')"
                        :rows="2"
                        :maxlength="280"
                        :hint="t('studio.bioHint')"
                        :disabled="!editable"
                        :error="errors.bio"
                    />
                    <BaseTextarea
                        v-model="form.story"
                        :label="t('studio.story')"
                        :rows="7"
                        :maxlength="5000"
                        :hint="t('studio.storyHint')"
                        :disabled="!editable"
                        :error="errors.story"
                    />

                    <ContentLanguage v-model="content.locale" :disabled="!editable" />
                    <fieldset class="m-0 flex flex-col gap-5 rounded-card border border-line p-5" :lang="otherLocale">
                        <legend class="px-1 text-[15px] font-semibold text-plum">
                            {{ t('content.translation', { lang: t(`content.langName.${otherLocale}`) }) }}
                        </legend>
                        <p class="m-0 text-[13px] text-ink-muted">{{ t('content.translationHint') }}</p>
                        <BaseTextarea
                            v-model="content.bio"
                            :label="t('studio.bio')"
                            :rows="2"
                            :maxlength="280"
                            :disabled="!editable"
                            :error="errors[`translations.${otherLocale}.bio`]"
                        />
                        <BaseTextarea
                            v-model="content.story"
                            :label="t('studio.story')"
                            :rows="5"
                            :maxlength="5000"
                            :disabled="!editable"
                            :error="errors[`translations.${otherLocale}.story`]"
                        />
                    </fieldset>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="flex flex-col gap-2">
                            <label for="country" class="text-[15px] font-semibold text-ink">{{ t('studio.country') }}</label>
                            <select
                                id="country"
                                v-model="form.country_code"
                                :disabled="!editable"
                                class="h-[52px] rounded-control border-[1.5px] border-line-strong bg-white px-4 text-base text-ink focus:border-plum focus:outline-none disabled:bg-lavender-50"
                            >
                                <option v-for="c in countries()" :key="c.code" :value="c.code">{{ c.name }}</option>
                            </select>
                        </div>
                        <BaseInput v-model="form.city" :label="t('studio.city')" autocomplete="address-level2" :disabled="!editable" :error="errors.city" />
                    </div>

                    <BaseInput
                        v-model="form.address"
                        :label="t('studio.address')"
                        autocomplete="street-address"
                        :hint="t('studio.addressHint')"
                        :disabled="!editable"
                        :error="errors.address"
                    />
                    <BaseInput
                        v-model="form.avatar_url"
                        :label="t('studio.photoUrl')"
                        type="text"
                        placeholder="https://…"
                        :hint="t('studio.photoHint')"
                        :disabled="!editable"
                        :error="errors.avatar_url"
                    />

                    <label class="flex items-start gap-3 text-[15px] text-ink">
                        <input v-model="form.follow_enabled" type="checkbox" class="mt-0.5 h-5 w-5 accent-plum" />
                        <span>
                            {{ t('studio.follow') }}
                            <span class="block text-[13px] text-ink-muted">{{ t('studio.followHint') }}</span>
                        </span>
                    </label>
                </fieldset>

                <section v-if="profile !== null" class="flex flex-col gap-5 border-t border-line pt-8" aria-labelledby="shipping-title">
                    <h2 id="shipping-title" class="m-0 text-xl font-bold text-plum">{{ t('studio.shippingTitle') }}</h2>
                    <p class="m-0 text-[15px] text-ink-muted">
                        {{ t('studio.shippingLead') }}
                    </p>
                    <div class="grid gap-6 md:grid-cols-[160px_1fr]">
                        <div class="flex flex-col gap-2">
                            <label for="delivery-currency" class="text-[15px] font-semibold text-ink">{{ t('studio.currency') }}</label>
                            <select
                                id="delivery-currency"
                                v-model="shipping.delivery_currency"
                                :disabled="!editable"
                                class="h-[52px] rounded-control border-[1.5px] border-line-strong bg-white px-4 text-base text-ink focus:border-plum focus:outline-none disabled:bg-lavender-50"
                            >
                                <option value="EUR">EUR</option>
                                <option value="USD">USD</option>
                                <option value="UAH">UAH</option>
                            </select>
                        </div>
                        <BaseInput
                            v-model="shipping.domestic"
                            :label="t('studio.domestic')"
                            :hint="t('studio.domesticHint')"
                            :disabled="!editable"
                            :error="errors.delivery_domestic_minor"
                        />
                    </div>
                    <p class="m-0 -mt-2 text-[13px] text-ink-muted">{{ t('studio.currencyNote') }}</p>

                    <label class="flex items-start gap-3 text-[15px] text-ink">
                        <input v-model="shipping.ships_abroad" type="checkbox" :disabled="!editable" class="mt-0.5 h-5 w-5 accent-plum" />
                        <span>{{ t('studio.shipsAbroad') }}</span>
                    </label>
                    <BaseInput
                        v-if="shipping.ships_abroad"
                        v-model="shipping.international"
                        :label="t('studio.international')"
                        :disabled="!editable"
                        :error="errors.delivery_international_minor"
                    />

                    <label class="flex items-start gap-3 text-[15px] text-ink">
                        <input v-model="shipping.cod_enabled" type="checkbox" :disabled="!editable" class="mt-0.5 h-5 w-5 accent-plum" />
                        <span>
                            {{ t('studio.cod') }}
                            <span class="block text-[13px] text-ink-muted">
                                {{ t('studio.codHint') }}
                            </span>
                        </span>
                    </label>
                </section>

                <div v-if="formError" role="alert" class="rounded-[10px] border border-kalyna/30 bg-kalyna/5 px-3.5 py-3 text-sm text-kalyna">{{ formError }}</div>
                <p v-else-if="notice" role="status" class="m-0 text-sm font-semibold text-[#2F4A1C]">{{ notice }}</p>

                <div v-if="editable" class="flex flex-wrap gap-3">
                    <BaseButton type="submit" size="lg" :variant="canSubmit ? 'secondary' : 'primary'" :loading="saving" :disabled="!dirty && profile !== null">
                        {{ profile === null ? t('studio.createDraft') : t('studio.save') }}
                    </BaseButton>
                    <BaseButton v-if="canSubmit || profile === null" size="lg" :loading="submitting" @click="submitForReview">{{ t('studio.submit') }}</BaseButton>
                </div>
            </form>
        </template>
    </div>
</template>
