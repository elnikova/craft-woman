<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;
use Modules\Marketplace\Domain\ProductStatus;
use Modules\Marketplace\Domain\ProductType;
use Modules\Marketplace\Infrastructure\Persistence\Category;
use Modules\Marketplace\Infrastructure\Persistence\Product;
use Modules\Marketplace\Infrastructure\Persistence\Tag;

/** Demo products for the demo makers (no photos — the UI shows embroidered placeholders). */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::query()->exists()) {
            return;
        }

        $demo = [
            'oksana@craftwoman.local' => [
                ['Плед з мериносу, ручне в’язання', 14500, 'Пледи', ProductType::Physical, ['Сірий' => 2, 'Лавандовий' => 1], ['вовна', 'плед']],
                ['Светр «Карпати»', 9800, 'Одяг', ProductType::Physical, ['S' => 1, 'M' => 3, 'L' => 0], ['вовна', 'светр']],
                ['Схема пледа «Ялинка», PDF', 700, 'Схеми та викрійки', ProductType::Digital, [], ['схема', 'вʼязання']],
            ],
            'marko@craftwoman.local' => [
                ['Чашка «Ранок», димлена глина', 2800, 'Посуд', ProductType::Physical, ['Стандартна' => 12], ['кераміка', 'чашка']],
                ['Глечик для молока', 4600, 'Посуд', ProductType::Physical, ['Стандартний' => 3], ['кераміка']],
            ],
            'iryna@craftwoman.local' => [
                ['Схема вишивки «Полтавські маки», PDF', 900, 'Схеми та викрійки', ProductType::Digital, [], ['вишивка', 'схема']],
                ['Вишита серветка «Барвінок»', 3500, 'Вишивка', ProductType::Physical, ['Стандартна' => 4], ['вишивка']],
            ],
            'luka@craftwoman.local' => [
                ['Шкіряний гаманець, ручний шов', 6200, 'Гаманці', ProductType::Physical, ['Коньячний' => 5, 'Чорний' => 2], ['шкіра', 'гаманець']],
            ],
        ];

        // English versions; the milk jug is left untranslated to show the fallback to the source language
        $english = [
            'Плед з мериносу, ручне в’язання' => 'Hand-knitted merino blanket',
            'Светр «Карпати»' => '"Carpathians" sweater',
            'Схема пледа «Ялинка», PDF' => '"Fir tree" blanket pattern, PDF',
            'Чашка «Ранок», димлена глина' => '"Morning" mug, smoked clay',
            'Схема вишивки «Полтавські маки», PDF' => '"Poltava poppies" embroidery pattern, PDF',
            'Вишита серветка «Барвінок»' => '"Periwinkle" embroidered napkin',
            'Шкіряний гаманець, ручний шов' => 'Hand-stitched leather wallet',
        ];

        foreach ($demo as $email => $products) {
            $maker = MakerProfile::query()->whereHas('user', fn ($q) => $q->where('email', $email))->first();
            if (! $maker) {
                continue;
            }

            foreach ($products as [$name, $price, $categoryName, $type, $variants, $tags]) {
                $product = new Product([
                    'maker_profile_uuid' => $maker->uuid,
                    'type' => $type,
                    'name' => $name,
                    'slug' => \Illuminate\Support\Str::slug($name),
                    'description' => "{$name}. Зроблено вручну в майстерні {$maker->display_name}. Кожна річ трохи відрізняється — це і є її характер.",
                    'price_amount_minor' => $price,
                    'currency' => 'EUR',
                    'production_time_days' => $type === ProductType::Physical ? 7 : null,
                    'shipping_info' => $type === ProductType::Physical ? 'Нова пошта по Україні, Укрпошта за кордон.' : null,
                ]);
                if (isset($english[$name])) {
                    $product->translations = ['en' => [
                        'name' => $english[$name],
                        'description' => "{$english[$name]}. Made by hand in {$maker->display_name}'s studio. Every piece is a little different, and that is its character.",
                        'shipping_info' => $type === ProductType::Physical ? 'Nova Poshta within Ukraine, Ukrposhta abroad.' : null,
                    ]];
                    $product->translations = $product->cleanTranslations($product->translations);
                }
                $product->status = ProductStatus::Published;
                $product->published_at = now()->subDays(random_int(0, 20));
                $product->save();

                if ($category = Category::query()->where('name', $categoryName)->first()) {
                    $product->categories()->attach($category->uuid, ['created_at' => now()]);
                }
                $product->tags()->sync(Tag::resolveNames($tags));

                $first = true;
                foreach ($variants as $variant => $stock) {
                    $product->variants()->create(['name' => $variant, 'currency' => 'EUR', 'stock' => $stock, 'is_default' => $first]);
                    $first = false;
                }
            }
        }
    }
}
