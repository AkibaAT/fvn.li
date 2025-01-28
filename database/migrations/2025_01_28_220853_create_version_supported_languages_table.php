<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create new table for language support
        Schema::create('version_supported_languages', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('game_version_id')->constrained()->cascadeOnDelete();
            $table->string('iso_code', 3);

            // Relate to ISO language table
            $table->foreign('iso_code')
                ->references('id')
                ->on('iso_639_3_languages')
                ->onDelete('restrict');

            $table->unique(['game_version_id', 'iso_code']);
        });

        // Migrate existing language support data
        DB::statement('
            INSERT INTO version_supported_languages (
                game_version_id,
                iso_code,
                created_at,
                updated_at
            )
            SELECT DISTINCT
                game_version_id,
                iso_code,
                NOW(),
                NOW()
            FROM version_language_stats
        ');

        // Remove rows that have no word stats
        DB::statement('
            DELETE FROM version_language_stats
            WHERE words IS NULL
        ');

        // Modify existing stats table to only include actual stats
        Schema::table('version_language_stats', function (Blueprint $table) {
            $table->integer('words')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        // Make stats columns nullable again
        Schema::table('version_language_stats', function (Blueprint $table) {
            $table->integer('words')->nullable()->change();
        });

        // Restore language tracking rows in stats table
        DB::statement('
            INSERT INTO version_language_stats (
                game_version_id,
                iso_code,
                blocks,
                words,
                menus,
                options,
                created_at,
                updated_at
            )
            SELECT
                vsl.game_version_id,
                vsl.iso_code,
                NULL,
                NULL,
                NULL,
                NULL,
                vsl.created_at,
                vsl.updated_at
            FROM version_supported_languages vsl
            LEFT JOIN version_language_stats vls
                ON vls.game_version_id = vsl.game_version_id
                AND vls.iso_code = vsl.iso_code
            WHERE vls.id IS NULL
        ');

        Schema::dropIfExists('version_supported_languages');
    }
};
