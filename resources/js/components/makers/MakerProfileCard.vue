<script setup lang="ts">
import type { MakerProfile } from '@/types/maker';
import MakerAvatar from './MakerAvatar.vue';

defineProps<{ maker: MakerProfile }>();
</script>

<template>
    <RouterLink :to="{ name: 'maker', params: { slug: maker.slug } }" class="group flex flex-col gap-3.5 text-inherit no-underline">
        <MakerAvatar :uuid="maker.uuid" :name="maker.display_name" :src="maker.avatar_url" />
        <div>
            <div class="text-xl font-semibold text-plum group-hover:underline group-hover:underline-offset-4">{{ maker.display_name }}</div>
            <div v-if="maker.city" class="mt-1 text-[15px] text-ink-muted">{{ maker.city }}</div>
            <p v-if="maker.bio" class="mt-2 mb-0 line-clamp-2 text-[15px] leading-snug text-ink">{{ maker.bio }}</p>
        </div>
    </RouterLink>
</template>
