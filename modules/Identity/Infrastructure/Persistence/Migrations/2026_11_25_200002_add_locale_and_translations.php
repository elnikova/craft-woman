<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preferred interface language; also used for e-mails later
        Schema::table('users', fn (Blueprint $t) => $t->char('locale', 2)->default('uk')->after('email'));

        Schema::table('maker_profiles', function (Blueprint $t) {
            $t->char('content_locale', 2)->default('uk');
            $t->json('translations')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('locale'));
        Schema::table('maker_profiles', fn (Blueprint $t) => $t->dropColumn(['content_locale', 'translations']));
    }
};
