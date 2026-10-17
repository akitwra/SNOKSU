<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('researches', function (Blueprint $t) {
            $t->text('public_summary')->nullable();
            $t->timestamp('published_at')->nullable();
        });
        Schema::table('research_user', fn (Blueprint $t) => $t->boolean('publication_consent')->default(false));
    }

    public function down(): void
    {
        Schema::table('researches', fn (Blueprint $t) => $t->dropColumn(['public_summary', 'published_at']));
        Schema::table('research_user', fn (Blueprint $t) => $t->dropColumn('publication_consent'));
    }
};
