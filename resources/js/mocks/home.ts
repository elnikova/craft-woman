import type { PostCardData, WorkshopRowData } from '@/types/catalog';

/** Sample data for the home page. Remove once the catalog API exists. */

export const popularCrafts = ['В’язання', 'Кераміка', 'Вишивка', 'Вироби зі шкіри', 'Ткацтво', 'Свічки'];

export const workshops: WorkshopRowData[] = [
    { id: 1, title: 'Різдвяна іграшка з фетру', makerName: 'Ірина Бойко', startsAt: '2026-12-03T18:00:00+02:00', mode: 'online', place: 'Zoom', capacity: 20, booked: 16, priceCents: 1500, currency: 'EUR' },
    { id: 2, title: 'Ліпимо тарілку на крузі', makerName: 'Марко Зайченко', startsAt: '2026-12-07T11:00:00+02:00', mode: 'offline', place: 'Опішня, вул. Гончарна', capacity: 10, booked: 2, priceCents: 4000, currency: 'EUR' },
    { id: 3, title: 'Шкіряний чохол для ключів', makerName: 'Лука Мельник', startsAt: '2026-12-12T19:00:00+02:00', mode: 'offline', place: 'Київ, Поділ', capacity: 8, booked: 8, priceCents: 3500, currency: 'EUR' },
];

export const posts: PostCardData[] = [
    { id: 1, makerName: 'Оксана Ткачук', text: 'Нова пряжа з Карпат прийшла — пробую на ній візерунок «ялинка». Покажу, що вийде, вже наступного тижня.', likes: 214, comments: 31, cover: { color: '#CFC2EE', pattern: 'ph-knit' } },
    { id: 2, makerName: 'Марко Зайченко', text: 'Перший випал серії чашок у димленій техніці. Кожна виходить трохи іншого відтінку — це і є найцікавіше.', likes: 178, comments: 22, cover: { color: '#EFD3A0', pattern: 'ph-weave' } },
];
