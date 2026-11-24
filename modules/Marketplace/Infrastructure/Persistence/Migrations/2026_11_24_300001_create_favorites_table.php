<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('product_uuid')->constrained('products', 'uuid')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_uuid', 'product_uuid']);
            $table->index('product_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
