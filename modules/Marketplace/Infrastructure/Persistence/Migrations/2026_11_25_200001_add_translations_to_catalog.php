<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bilingual content (domain-rules.md §42): text is written in one source language
 * (`content_locale`) and may carry translations as JSON keyed by locale.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['categories', 'products'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->char('content_locale', 2)->default('uk');
                $t->json('translations')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['categories', 'products'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['content_locale', 'translations']));
        }
    }
};
