<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A cart line can also be seats at a workshop (quantity = seats). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignUuid('workshop_uuid')->nullable()->after('course_uuid')->constrained('workshops', 'uuid')->cascadeOnDelete();
            $table->unique(['cart_uuid', 'workshop_uuid']);
        });
        // order_items.workshop_uuid exists since the orders migration (no FK: items are snapshots)
        Schema::table('order_items', fn (Blueprint $table) => $table->index('workshop_uuid'));
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropIndex(['workshop_uuid']));
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(['cart_uuid', 'workshop_uuid']);
            $table->dropConstrainedForeignId('workshop_uuid');
        });
    }
};
