<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A cart line is either a product (+ variant) or a course. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->uuid('product_uuid')->nullable()->change();
            $table->foreignUuid('course_uuid')->nullable()->after('product_variant_uuid')->constrained('courses', 'uuid')->cascadeOnDelete();
            $table->unique(['cart_uuid', 'course_uuid']);
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(['cart_uuid', 'course_uuid']);
            $table->dropConstrainedForeignId('course_uuid');
        });
    }
};
