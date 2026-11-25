<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Education module (postgresql-schema.md §5). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('maker_profile_uuid')->constrained('maker_profiles', 'uuid')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->unsignedInteger('price_amount_minor')->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->string('access_type', 16)->default('lifetime');
            $table->unsignedSmallInteger('access_days')->nullable();
            $table->boolean('certificate_enabled')->default(true);
            $table->string('status', 32)->default('draft')->index();
            $table->text('review_note')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->char('content_locale', 2)->default('uk');
            $table->json('translations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['maker_profile_uuid', 'status']);
        });

        Schema::create('course_modules', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('course_uuid')->constrained('courses', 'uuid')->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['course_uuid', 'sort_order']);
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('course_module_uuid')->constrained('course_modules', 'uuid')->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(true);
            // A free preview lesson is open to everyone on the course page
            $table->boolean('is_preview')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['course_module_uuid', 'sort_order']);
        });

        Schema::create('lesson_materials', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('lesson_uuid')->constrained('lessons', 'uuid')->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('title', 160)->nullable();
            $table->longText('content')->nullable();
            $table->string('external_url', 500)->nullable();
            $table->string('storage_disk', 32)->nullable();
            $table->string('storage_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['lesson_uuid', 'sort_order']);
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('course_uuid')->constrained('courses', 'uuid')->restrictOnDelete();
            // Orders module reference, kept without a foreign key (module boundary)
            $table->uuid('order_item_uuid')->nullable()->index();
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Buying the course again extends the same enrollment and keeps progress
            $table->unique(['user_uuid', 'course_uuid']);
        });

        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('enrollment_uuid')->constrained('enrollments', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('lesson_uuid')->constrained('lessons', 'uuid')->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['enrollment_uuid', 'lesson_uuid']);
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('enrollment_uuid')->unique()->constrained('enrollments', 'uuid')->cascadeOnDelete();
            $table->string('certificate_number', 32)->unique();
            // Snapshots: the certificate must read the same even if names change later
            $table->string('holder_name', 160);
            $table->string('course_name', 160);
            $table->string('maker_name', 160);
            $table->timestamp('issued_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['certificates', 'lesson_progress', 'enrollments', 'lesson_materials', 'lessons', 'course_modules', 'courses'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
