<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;
use Modules\Marketplace\Domain\ProductStatus;
use Modules\Marketplace\Domain\ProductType;
use Modules\Marketplace\Infrastructure\Persistence\Product;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = ucfirst(fake()->words(3, true));

        return [
            'maker_profile_uuid' => MakerProfile::factory()->approved(),
            'type' => ProductType::Physical,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'description' => fake()->paragraph(3),
            'price_amount_minor' => fake()->numberBetween(900, 25000),
            'currency' => 'EUR',
            'production_time_days' => 5,
        ];
    }

    public function digital(): static
    {
        return $this->state(['type' => ProductType::Digital, 'production_time_days' => null]);
    }

    public function status(ProductStatus $status): static
    {
        return $this->afterMaking(function (Product $p) use ($status) {
            $p->status = $status;
            $p->published_at = $status === ProductStatus::Published ? now() : null;
        });
    }

    public function published(): static
    {
        return $this->status(ProductStatus::Published);
    }

    /** Physical products get a default variant, like ProductService does. */
    public function configure(): static
    {
        return $this->afterCreating(function (Product $p) {
            if ($p->type === ProductType::Physical && ! $p->variants()->exists()) {
                $p->variants()->create(['name' => 'Стандартний', 'currency' => $p->currency, 'stock' => 5, 'is_default' => true]);
            }
        });
    }
}
