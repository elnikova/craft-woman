<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import ProductCover from '@/components/products/ProductCover.vue';
import type { CourseCard } from '@/types/course';
import { accessLabel } from '@/utils/course';
import { formatPrice, plural } from '@/utils/format';

defineProps<{ course: CourseCard }>();
const { t } = useI18n();
</script>

<template>
    <article class="group flex flex-col overflow-hidden rounded-[20px] bg-white ring-1 ring-line">
        <RouterLink :to="{ name: 'course', params: { slug: course.slug } }" tabindex="-1" aria-hidden="true">
            <ProductCover :uuid="course.uuid" :name="course.name" :src="course.cover_url" ratio="wide" class="!rounded-none" />
        </RouterLink>
        <div class="flex flex-1 flex-col gap-2 px-6 pt-5 pb-6">
            <div v-if="course.maker" class="text-sm text-ink-soft">{{ course.maker.display_name }}</div>
            <RouterLink
                :to="{ name: 'course', params: { slug: course.slug } }"
                class="text-[21px] leading-snug font-semibold text-plum no-underline group-hover:underline group-hover:underline-offset-4"
                :lang="course.content_locale"
            >
                {{ course.name }}
            </RouterLink>
            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-ink-soft">
                <span>{{ plural(course.lessons_count, 'lessons') }}</span>
                <span>{{ accessLabel(course) }}</span>
                <span>{{ course.certificate_enabled ? t('courses.certificate') : t('courses.noCertificate') }}</span>
            </div>
            <div class="mt-auto pt-2 text-lg font-bold text-plum">{{ formatPrice(course.price.amount_minor, course.price.currency) }}</div>
        </div>
    </article>
</template>
