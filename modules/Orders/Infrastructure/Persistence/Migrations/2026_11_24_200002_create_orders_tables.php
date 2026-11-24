<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * postgresql-schema.md §7.1–7.6: carts, cart_items, orders, order_groups, order_items, deliveries.
 * Additions: orders.payment_method, carts.token for guest carts, deliveries.phone, order_groups.cancel_reason.
 * Cross-module references (users, maker_profiles, products, variants) are UUIDs without FKs on
 * order snapshots, so history survives deletion of the source (§7.5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('user_uuid')->nullable()->unique()->constrained('users', 'uuid')->cascadeOnDelete();
            $table->char('currency', 3)->nullable(); // set by the first item
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('cart_uuid')->constrained('carts', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('product_uuid')->constrained('products', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('product_variant_uuid')->nullable()->constrained('product_variants', 'uuid')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_amount_minor');
            $table->char('currency', 3);
            $table->timestamps();

            $table->unique(['cart_uuid', 'product_uuid', 'product_variant_uuid']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('user_uuid')->nullable()->constrained('users', 'uuid')->nullOnDelete();
            $table->string('order_number', 20)->unique();
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_amount_minor');
            $table->unsignedBigInteger('delivery_amount_minor');
            $table->unsignedBigInteger('total_amount_minor');
            $table->string('status', 32)->index();
            $table->string('payment_method', 32);
            $table->string('billing_email');
            $table->timestamp('placed_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_groups', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('order_uuid')->constrained('orders', 'uuid')->cascadeOnDelete();
            $table->uuid('maker_profile_uuid')->index();
            $table->string('maker_name'); // snapshot
            $table->unsignedBigInteger('subtotal_amount_minor');
            $table->unsignedBigInteger('delivery_amount_minor');
            $table->unsignedBigInteger('total_amount_minor');
            $table->string('status', 32)->index();
            $table->string('cancel_reason', 1000)->nullable();
            $table->timestamps();

            $table->unique(['order_uuid', 'maker_profile_uuid']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('order_group_uuid')->constrained('order_groups', 'uuid')->cascadeOnDelete();
            $table->uuid('product_uuid')->nullable();
            $table->uuid('product_variant_uuid')->nullable();
            $table->uuid('course_uuid')->nullable();
            $table->uuid('workshop_uuid')->nullable();
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->unsignedBigInteger('unit_price_amount_minor');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('total_amount_minor');
            $table->char('currency', 3);
            $table->json('metadata')->nullable(); // type, slug, image_url at purchase time
            $table->timestamps();
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('order_group_uuid')->unique()->constrained('order_groups', 'uuid')->cascadeOnDelete();
            $table->string('recipient_name');
            $table->string('phone', 32);
            $table->char('country_code', 2);
            $table->string('city');
            $table->string('address');
            $table->string('postal_code', 20)->nullable();
            $table->string('tracking_number', 64)->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['deliveries', 'order_items', 'order_groups', 'orders', 'cart_items', 'carts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
