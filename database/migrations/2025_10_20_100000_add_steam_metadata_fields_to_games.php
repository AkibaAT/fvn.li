<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Steam-specific metadata fields
            $table->string('developer')->nullable()->after('authors')->comment('Game developer(s)');
            $table->jsonb('steam_genres')->nullable()->after('developer')->comment('Steam genre tags');
            $table->text('steam_languages')->nullable()->after('steam_genres')->comment('Supported languages from Steam');
            $table->jsonb('steam_user_tags')->nullable()->after('steam_languages')->comment('User-defined tags from Steam');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn([
                'developer',
                'steam_genres',
                'steam_languages',
                'steam_user_tags',
            ]);
        });
    }
};

