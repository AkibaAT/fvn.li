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
        Schema::table('ratings', function (Blueprint $table) {
            // Track external reviews from Steam, etc.
            $table->string('external_id')->nullable()->after('id')->comment('External platform review ID (e.g., Steam recommendationid)');
            $table->enum('source_platform', ['fvn_li', 'steam', 'other'])->default('fvn_li')->after('external_id')->comment('Platform where the review originated');
            $table->jsonb('external_metadata')->nullable()->after('review')->comment('Additional metadata from external platforms (playtime, votes, etc.)');
            
            // Index for finding existing external reviews
            $table->unique(['source_platform', 'external_id'], 'ratings_source_platform_external_id_unique');
            $table->index('source_platform');
        });

        Schema::table('raters', function (Blueprint $table) {
            // Track external platform user IDs
            $table->string('steam_id')->nullable()->after('user_id')->comment('Steam user ID (SteamID64)');
            $table->string('external_platform')->nullable()->after('steam_id')->comment('Primary external platform for this rater');
            
            // Index for finding raters by Steam ID
            $table->index('steam_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->dropUnique('ratings_source_platform_external_id_unique');
            $table->dropIndex(['source_platform']);
            $table->dropColumn(['external_id', 'source_platform', 'external_metadata']);
        });

        Schema::table('raters', function (Blueprint $table) {
            $table->dropIndex(['steam_id']);
            $table->dropColumn(['steam_id', 'external_platform']);
        });
    }
};

