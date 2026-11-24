<script setup lang="ts">
import type { MakerCardData } from '@/types/catalog';
import { formatRating, plural } from '@/utils/format';

defineProps<{ maker: MakerCardData }>();
</script>

<template>
    <RouterLink to="/" class="group flex flex-col gap-3.5 text-inherit no-underline">
        <div
            class="flex aspect-[4/5] items-end rounded-card p-4"
            :class="maker.cover.pattern"
            :style="{ backgroundColor: maker.cover.color }"
        >
            <span class="rounded-lg bg-white/90 px-2.5 py-1.5 text-[13px] font-medium text-ink">Фото майстра</span>
        </div>
        <div>
            <div class="text-xl font-semibold text-plum group-hover:underline group-hover:underline-offset-4">{{ maker.name }}</div>
            <div class="mt-1 text-[15px] text-ink-muted">{{ maker.craft }} · {{ maker.city }}</div>
            <div class="mt-2 flex items-center gap-1.5 text-sm text-ink">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="#E0A020" aria-hidden="true">
                    <path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z" />
                </svg>
                <span>{{ formatRating(maker.rating) }}</span>
                <span class="text-ink-muted">({{ plural(maker.reviewsCount, ['відгук', 'відгуки', 'відгуків']) }})</span>
            </div>
        </div>
    </RouterLink>
</template>
