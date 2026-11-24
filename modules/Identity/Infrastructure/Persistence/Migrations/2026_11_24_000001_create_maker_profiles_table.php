<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * postgresql-schema.md §3.4 maker_profiles
 * + review_note: the reviewer's message for a rejected or suspended profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maker_profiles', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('user_uuid')->unique()->constrained('users', 'uuid')->cascadeOnDelete();
            $table->string('display_name', 120);
            $table->string('slug', 140)->unique();
            $table->string('bio', 280)->nullable();
            $table->text('story')->nullable();
            $table->string('avatar_url')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('address')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->boolean('follow_enabled')->default(true);
            $table->text('review_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maker_profiles');
    }
};
