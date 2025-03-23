<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('language_mappings', function (Blueprint $table) {
            $table->foreignId('game_id')->nullable()->after('id')->constrained('games')->cascadeOnDelete();
            // Add a unique constraint to ensure that a game_language_key is unique per game
            $table->unique(['game_id', 'game_language_key'], 'language_mappings_game_id_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('language_mappings', function (Blueprint $table) {
            $table->dropForeign(['game_id']);
            $table->dropUnique('language_mappings_game_id_key_unique');
            $table->dropColumn('game_id');
        });
    }
};
