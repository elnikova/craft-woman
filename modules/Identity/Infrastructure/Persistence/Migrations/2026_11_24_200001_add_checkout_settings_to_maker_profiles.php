<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maker checkout settings (PRD §10, domain-rules.md §25):
 * - payment on delivery is opt-in per Maker;
 * - flat delivery fee per order group, domestic and international (null = does not ship abroad).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maker_profiles', function (Blueprint $table) {
            $table->boolean('cod_enabled')->default(false)->after('follow_enabled');
            $table->char('delivery_currency', 3)->default('EUR')->after('cod_enabled');
            $table->unsignedBigInteger('delivery_domestic_minor')->default(0)->after('delivery_currency');
            $table->unsignedBigInteger('delivery_international_minor')->nullable()->after('delivery_domestic_minor');
        });
    }

    public function down(): void
    {
        Schema::table('maker_profiles', function (Blueprint $table) {
            $table->dropColumn(['cod_enabled', 'delivery_currency', 'delivery_domestic_minor', 'delivery_international_minor']);
        });
    }
};
