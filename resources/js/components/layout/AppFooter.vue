<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import OrnamentBand from '@/components/ornament/OrnamentBand.vue';

const { t } = useI18n();

// Message keys; the pages behind these links don't exist yet
const columns = [
    { title: 'footer.buyers', links: ['footer.howToBuy', 'footer.delivery', 'footer.myOrders'] },
    { title: 'footer.makers', links: ['footer.openStudio', 'footer.rules'] },
    { title: '', links: ['footer.about', 'footer.contacts'] },
];
</script>

<template>
    <footer>
        <OrnamentBand start="mountains" />
        <div class="mx-auto flex max-w-[1280px] flex-wrap justify-between gap-12 px-6 py-12 text-[15px] text-ink-muted md:px-8">
            <div class="font-flair text-[22px] font-bold text-plum">Craft Woman</div>
            <div class="flex flex-wrap gap-12">
                <div v-for="col in columns" :key="col.title || 'brand'" class="flex flex-col gap-2.5">
                    <strong class="text-ink">{{ col.title ? t(col.title) : 'Craft Woman' }}</strong>
                    <a v-for="link in col.links" :key="link" href="#" class="text-plum hover:text-violet">{{ t(link) }}</a>
                </div>
            </div>
        </div>
    </footer>
</template>
