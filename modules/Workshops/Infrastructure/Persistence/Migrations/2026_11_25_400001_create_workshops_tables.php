<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workshops module (postgresql-schema.md §6). Differences from the schema doc:
 * capacity lives on the workshop (a booking covers every session, so per-session capacity
 * would always collapse to the smallest one); bookings carry `seats` and a `pending` status
 * so unpaid orders hold seats; meeting link / address are shown to participants only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshops', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('maker_profile_uuid')->constrained('maker_profiles', 'uuid')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->unsignedInteger('price_amount_minor')->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->string('format', 16)->default('offline');
            $table->string('timezone', 64)->default('Europe/Kyiv');
            $table->unsignedSmallInteger('capacity')->default(0);
            $table->char('location_country_code', 2)->nullable();
            $table->string('location_city', 120)->nullable();
            $table->string('location_address', 255)->nullable();
            $table->string('external_url', 500)->nullable();
            $table->text('participant_note')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->text('review_note')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->char('content_locale', 2)->default('uk');
            $table->json('translations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['maker_profile_uuid', 'status']);
        });

        Schema::create('workshop_sessions', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('workshop_uuid')->constrained('workshops', 'uuid')->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();

            $table->index(['workshop_uuid', 'starts_at']);
        });

        Schema::create('workshop_bookings', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('workshop_uuid')->constrained('workshops', 'uuid')->restrictOnDelete();
            // No FK: Orders owns order_items and is migrated after this module
            $table->uuid('order_item_uuid')->nullable()->unique();
            $table->unsignedSmallInteger('seats')->default(1);
            $table->string('status', 16)->default('pending');
            $table->timestamp('booked_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['workshop_uuid', 'status']);
            $table->index(['user_uuid', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_bookings');
        Schema::dropIfExists('workshop_sessions');
        Schema::dropIfExists('workshops');
    }
};
