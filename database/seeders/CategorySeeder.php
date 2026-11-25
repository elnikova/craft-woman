<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Marketplace\Application\Services\CategoryService;
use Modules\Marketplace\Infrastructure\Persistence\Category;

/** Starter categories from domain-model.md §5.4: Ukrainian source names with English translations. */
class CategorySeeder extends Seeder
{
    public function run(CategoryService $categories): void
    {
        if (Category::query()->exists()) {
            return;
        }

        $tree = [
            ['Прикраси', 'Jewellery', [['Сережки', 'Earrings'], ['Намиста й підвіски', 'Necklaces & pendants'], ['Браслети', 'Bracelets']]],
            ['Кераміка', 'Ceramics', [['Посуд', 'Tableware'], ['Декор', 'Decor']]],
            ['Текстиль', 'Textiles', [['Вишивка', 'Embroidery'], ['Ткацтво', 'Weaving']]],
            ['В’язання', 'Knitting', [['Одяг', 'Clothing'], ['Пледи', 'Blankets'], ['Іграшки', 'Toys']]],
            ['Шкіра', 'Leather', [['Гаманці', 'Wallets'], ['Сумки', 'Bags']]],
            ['Дерево', 'Wood', []],
            ['Свічки', 'Candles', []],
            ['Живопис', 'Painting', []],
            ['Схеми та викрійки', 'Patterns', []],
        ];

        $make = fn (string $uk, string $en, ?string $parent = null) => $categories->create(
            ['name' => $uk, 'parent_uuid' => $parent, 'translations' => ['en' => ['name' => $en]]]
        );

        foreach ($tree as [$uk, $en, $children]) {
            $parent = $make($uk, $en);
            foreach ($children as [$childUk, $childEn]) {
                $make($childUk, $childEn, $parent->uuid);
            }
        }
    }
}
