<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** postgresql-schema.md §4.5 categories, §4.6 categorizables, §4.7 tags */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('parent_uuid')->nullable();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['parent_uuid', 'sort_order']);
        });

        // Self-reference added after the table exists: PostgreSQL needs the primary key in place first
        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('parent_uuid')->references('uuid')->on('categories')->restrictOnDelete();
        });

        Schema::create('categorizables', function (Blueprint $table) {
            $table->foreignUuid('category_uuid')->constrained('categories', 'uuid')->cascadeOnDelete();
            $table->uuid('categorizable_uuid');
            $table->string('categorizable_type', 64);
            $table->timestamp('created_at')->nullable();

            $table->primary(['category_uuid', 'categorizable_uuid', 'categorizable_type']);
            $table->index(['categorizable_type', 'categorizable_uuid']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('name', 50);
            $table->string('slug', 60)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categorizables');
        Schema::dropIfExists('categories');
    }
};
