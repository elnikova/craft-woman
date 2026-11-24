<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Marketplace\Application\Services\CategoryService;
use Modules\Marketplace\Infrastructure\Persistence\Category;

/** Starter categories from domain-model.md §5.4, in Ukrainian, with a few subcategories. */
class CategorySeeder extends Seeder
{
    public function run(CategoryService $categories): void
    {
        if (Category::query()->exists()) {
            return;
        }

        $tree = [
            'Прикраси' => ['Сережки', 'Намиста й підвіски', 'Браслети'],
            'Кераміка' => ['Посуд', 'Декор'],
            'Текстиль' => ['Вишивка', 'Ткацтво'],
            'В’язання' => ['Одяг', 'Пледи', 'Іграшки'],
            'Шкіра' => ['Гаманці', 'Сумки'],
            'Дерево' => [],
            'Свічки' => [],
            'Живопис' => [],
            'Схеми та викрійки' => [],
        ];

        foreach ($tree as $name => $children) {
            $parent = $categories->create(['name' => $name]);
            foreach ($children as $child) {
                $categories->create(['name' => $child, 'parent_uuid' => $parent->uuid]);
            }
        }
    }
}
