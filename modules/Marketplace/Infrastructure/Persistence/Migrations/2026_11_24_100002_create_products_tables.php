<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * postgresql-schema.md §4.1–4.4, §4.8: products, product_variants, product_images,
 * product_files, product_tags. Plus products.review_note for the moderator's message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            // Cross-module reference by UUID only (Identity owns maker_profiles)
            $table->foreignUuid('maker_profile_uuid')->constrained('maker_profiles', 'uuid')->restrictOnDelete();
            $table->string('type', 16);
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_amount_minor')->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->string('status', 32)->default('draft');
            $table->unsignedSmallInteger('production_time_days')->nullable();
            $table->string('shipping_info', 1000)->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['maker_profile_uuid', 'status']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('product_uuid')->constrained('products', 'uuid')->cascadeOnDelete();
            $table->string('sku', 64)->nullable();
            $table->string('name', 120);
            $table->unsignedBigInteger('price_amount_minor')->nullable(); // null → product price
            $table->char('currency', 3);
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('weight_grams')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_stock_non_negative CHECK (stock >= 0)');
        }

        Schema::create('product_images', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('product_uuid')->constrained('products', 'uuid')->cascadeOnDelete();
            $table->string('file_url');
            $table->string('storage_path');
            $table->string('alt_text', 160)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_files', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('product_uuid')->constrained('products', 'uuid')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('storage_disk', 32);
            $table->string('storage_path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_tags', function (Blueprint $table) {
            $table->foreignUuid('product_uuid')->constrained('products', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('tag_uuid')->constrained('tags', 'uuid')->cascadeOnDelete();
            $table->primary(['product_uuid', 'tag_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_tags');
        Schema::dropIfExists('product_files');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
