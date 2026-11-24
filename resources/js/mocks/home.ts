import type { CourseCardData, MakerCardData, PostCardData, ProductCardData, WorkshopRowData } from '@/types/catalog';

/** Приклад даних для головної. Видалити, коли з'явиться API каталогу. */

export const popularCrafts = ['В’язання', 'Кераміка', 'Вишивка', 'Вироби зі шкіри', 'Ткацтво', 'Свічки'];

export const makers: MakerCardData[] = [
    { id: 1, name: 'Оксана Ткачук', craft: 'В’язання', city: 'Львів', rating: 4.9, reviewsCount: 128, cover: { color: '#CFC2EE', pattern: 'ph-knit' } },
    { id: 2, name: 'Марко Зайченко', craft: 'Кераміка', city: 'Опішня', rating: 4.8, reviewsCount: 64, cover: { color: '#EFD3A0', pattern: 'ph-weave' } },
    { id: 3, name: 'Ірина Бойко', craft: 'Вишивка', city: 'Онлайн', rating: 5.0, reviewsCount: 41, cover: { color: '#CBD5B6', pattern: 'ph-stitch' } },
    { id: 4, name: 'Лука Мельник', craft: 'Вироби зі шкіри', city: 'Київ', rating: 4.7, reviewsCount: 87, cover: { color: '#DCC5B8', pattern: 'ph-knit' } },
];

export const products: ProductCardData[] = [
    { id: 1, name: 'Плед з мериносу, ручне в’язання', makerName: 'Оксана Ткачук', priceCents: 14500, currency: 'EUR', isDigital: false, cover: { color: '#DBD0F3', pattern: 'ph-knit' } },
    { id: 2, name: 'Чашка «Ранок», димлена глина', makerName: 'Марко Зайченко', priceCents: 2800, currency: 'EUR', isDigital: false, cover: { color: '#F0D9AA', pattern: 'ph-weave' } },
    { id: 3, name: 'Схема вишивки «Полтавські маки», PDF', makerName: 'Ірина Бойко', priceCents: 900, currency: 'EUR', isDigital: true, cover: { color: '#D4DDC2', pattern: 'ph-stitch' } },
    { id: 4, name: 'Шкіряний гаманець, ручний шов', makerName: 'Лука Мельник', priceCents: 6200, currency: 'EUR', isDigital: false, cover: { color: '#E2CFC3', pattern: 'ph-weave' } },
];

export const courses: CourseCardData[] = [
    { id: 1, title: 'В’язання спицями з нуля', makerName: 'Оксана Ткачук', lessonsCount: 24, accessMonths: null, hasCertificate: true, priceCents: 4900, currency: 'EUR', cover: { color: '#CFC2EE', pattern: 'ph-knit' } },
    { id: 2, title: 'Гончарний круг: перші 10 форм', makerName: 'Марко Зайченко', lessonsCount: 12, accessMonths: 6, hasCertificate: false, priceCents: 3900, currency: 'EUR', cover: { color: '#EFD3A0', pattern: 'ph-weave' } },
    { id: 3, title: 'Гладь і хрестик: основи вишивки', makerName: 'Ірина Бойко', lessonsCount: 18, accessMonths: null, hasCertificate: true, priceCents: 3500, currency: 'EUR', cover: { color: '#CBD5B6', pattern: 'ph-stitch' } },
];

export const workshops: WorkshopRowData[] = [
    { id: 1, title: 'Різдвяна іграшка з фетру', makerName: 'Ірина Бойко', startsAt: '2026-12-03T18:00:00+02:00', mode: 'online', place: 'Zoom', capacity: 20, booked: 16, priceCents: 1500, currency: 'EUR' },
    { id: 2, title: 'Ліпимо тарілку на крузі', makerName: 'Марко Зайченко', startsAt: '2026-12-07T11:00:00+02:00', mode: 'offline', place: 'Опішня, вул. Гончарна', capacity: 10, booked: 2, priceCents: 4000, currency: 'EUR' },
    { id: 3, title: 'Шкіряний чохол для ключів', makerName: 'Лука Мельник', startsAt: '2026-12-12T19:00:00+02:00', mode: 'offline', place: 'Київ, Поділ', capacity: 8, booked: 8, priceCents: 3500, currency: 'EUR' },
];

export const posts: PostCardData[] = [
    { id: 1, makerName: 'Оксана Ткачук', text: 'Нова пряжа з Карпат прийшла — пробую на ній візерунок «ялинка». Покажу, що вийде, вже наступного тижня.', likes: 214, comments: 31, cover: { color: '#CFC2EE', pattern: 'ph-knit' } },
    { id: 2, makerName: 'Марко Зайченко', text: 'Перший випал серії чашок у димленій техніці. Кожна виходить трохи іншого відтінку — це і є найцікавіше.', likes: 178, comments: 22, cover: { color: '#EFD3A0', pattern: 'ph-weave' } },
];
