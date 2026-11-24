<script setup lang="ts">
import type { PostCardData } from '@/types/catalog';
import { plural } from '@/utils/format';

defineProps<{ post: PostCardData }>();
</script>

<template>
    <article class="flex flex-wrap gap-5 rounded-[20px] border border-line p-6">
        <div class="aspect-square w-40 shrink-0 rounded-[14px]" :class="post.cover.pattern" :style="{ backgroundColor: post.cover.color }" />
        <div class="flex min-w-0 flex-[1_1_200px] flex-col gap-2.5">
            <div class="text-[15px] font-semibold text-plum">{{ post.makerName }}</div>
            <p class="m-0 text-base leading-relaxed text-ink">{{ post.text }}</p>
            <div class="mt-auto flex gap-4 text-sm text-ink-muted">
                <span>{{ plural(post.likes, ['вподобання', 'вподобання', 'вподобань']) }}</span>
                <span>{{ plural(post.comments, ['коментар', 'коментарі', 'коментарів']) }}</span>
            </div>
        </div>
    </article>
</template>
