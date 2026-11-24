<script setup lang="ts">
import { computed } from 'vue';
import type { CourseCardData } from '@/types/catalog';
import { formatPrice, plural } from '@/utils/format';

const props = defineProps<{ course: CourseCardData }>();

const access = computed(() =>
    props.course.accessMonths === null
        ? 'Доступ назавжди'
        : `Доступ ${plural(props.course.accessMonths, ['місяць', 'місяці', 'місяців'])}`,
);
</script>

<template>
    <RouterLink to="/" class="group flex flex-col overflow-hidden rounded-[20px] bg-white text-inherit no-underline">
        <div class="aspect-video" :class="course.cover.pattern" :style="{ backgroundColor: course.cover.color }" />
        <div class="flex flex-col gap-2 px-6 pt-5 pb-6">
            <div class="text-sm text-ink-soft">{{ course.makerName }}</div>
            <div class="text-[21px] leading-snug font-semibold text-plum group-hover:underline group-hover:underline-offset-4">
                {{ course.title }}
            </div>
            <div class="mt-2 flex flex-wrap gap-4 text-sm text-ink-soft">
                <span>{{ plural(course.lessonsCount, ['урок', 'уроки', 'уроків']) }}</span>
                <span>{{ access }}</span>
                <span>{{ course.hasCertificate ? 'Сертифікат' : 'Без сертифіката' }}</span>
            </div>
            <div class="mt-2 text-lg font-bold text-plum">{{ formatPrice(course.priceCents, course.currency) }}</div>
        </div>
    </RouterLink>
</template>
