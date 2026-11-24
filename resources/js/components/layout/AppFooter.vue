<script setup lang="ts">
import OrnamentBand from '@/components/ornament/OrnamentBand.vue';

const columns = [
    { title: 'Покупцям', links: ['Як купувати', 'Доставка й оплата', 'Мої замовлення'] },
    { title: 'Майстрам', links: ['Відкрити майстерню', 'Правила платформи'] },
    { title: 'Craft Woman', links: ['Про нас', 'Контакти'] },
];
</script>

<template>
    <footer>
        <OrnamentBand start="mountains" />
        <div class="mx-auto flex max-w-[1280px] flex-wrap justify-between gap-12 px-6 py-12 text-[15px] text-ink-muted md:px-8">
            <div class="font-flair text-[22px] font-bold text-plum">Craft Woman</div>
            <div class="flex flex-wrap gap-12">
                <div v-for="col in columns" :key="col.title" class="flex flex-col gap-2.5">
                    <strong class="text-ink">{{ col.title }}</strong>
                    <a v-for="link in col.links" :key="link" href="#" class="text-plum hover:text-violet">{{ link }}</a>
                </div>
            </div>
        </div>
    </footer>
</template>
