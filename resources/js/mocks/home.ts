import type { PostCardData } from '@/types/catalog';

/** Sample data for the home page. Remove once the catalog API exists. */

export const popularCrafts = ['В’язання', 'Кераміка', 'Вишивка', 'Вироби зі шкіри', 'Ткацтво', 'Свічки'];

export const posts: PostCardData[] = [
    { id: 1, makerName: 'Оксана Ткачук', text: 'Нова пряжа з Карпат прийшла — пробую на ній візерунок «ялинка». Покажу, що вийде, вже наступного тижня.', likes: 214, comments: 31, cover: { color: '#CFC2EE', pattern: 'ph-knit' } },
    { id: 2, makerName: 'Марко Зайченко', text: 'Перший випал серії чашок у димленій техніці. Кожна виходить трохи іншого відтінку — це і є найцікавіше.', likes: 178, comments: 22, cover: { color: '#EFD3A0', pattern: 'ph-weave' } },
];
