<script setup lang="ts">
import type { IconName } from '@/config/accountNav';

defineProps<{ name: IconName; size?: number }>();

/** Simple line icons (stroke = currentColor) */
const PATHS: Record<IconName, string[]> = {
    home: ['M4 11l8-7 8 7', 'M6 10v10h12V10'],
    bag: ['M5 8h14l-1 12H6L5 8z', 'M9 8V6a3 3 0 0 1 6 0v2'],
    book: ['M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2V5z', 'M4 19a2 2 0 0 1 2-2h13'],
    calendar: ['M4 6h16v14H4z', 'M4 10h16', 'M8 3v4M16 3v4'],
    heart: ['M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z'],
    settings: ['M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z', 'M19 12a7 7 0 0 0-.1-1.2l2-1.5-2-3.4-2.3.9a7 7 0 0 0-2-1.2L14 3h-4l-.6 2.6a7 7 0 0 0-2 1.2l-2.3-.9-2 3.4 2 1.5a7 7 0 0 0 0 2.4l-2 1.5 2 3.4 2.3-.9a7 7 0 0 0 2 1.2L10 21h4l.6-2.6a7 7 0 0 0 2-1.2l2.3.9 2-3.4-2-1.5c.1-.4.1-.8.1-1.2z'],
    store: ['M4 9l1.5-5h13L20 9', 'M4 9h16v2a3 3 0 0 1-5.3 2A3 3 0 0 1 12 14a3 3 0 0 1-2.7-1A3 3 0 0 1 4 11V9z', 'M5 14v6h14v-6'],
    box: ['M4 7l8-4 8 4v10l-8 4-8-4V7z', 'M4 7l8 4 8-4', 'M12 11v10'],
    chart: ['M4 20V4', 'M4 20h16', 'M8 16v-4M12 16V8M16 16v-6'],
    check: ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z', 'M8 12l3 3 5-6'],
    users: ['M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z', 'M2 21a7 7 0 0 1 14 0', 'M16 3.5a4 4 0 0 1 0 7', 'M22 21a7 7 0 0 0-4-6.3'],
    tag: ['M3 12V4h8l10 10-8 8L3 12z', 'M8 8h.01'],
    flag: ['M5 21V4', 'M5 4h11l-2 4 2 4H5'],
};
</script>

<template>
    <svg
        :width="size ?? 20"
        :height="size ?? 20"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path v-for="(d, i) in PATHS[name]" :key="i" :d="d" />
    </svg>
</template>
