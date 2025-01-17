<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Set default values for games table
        Schema::table('games', function (Blueprint $table) {
            // Update existing rows
            DB::statement("UPDATE games SET status = 'In development' WHERE status IS NULL");
            DB::statement("UPDATE games SET game_engine = 'unknown' WHERE game_engine IS NULL");

            // Set column defaults
            $table->string('status', 50)->default('In development')->nullable(false)->change();
            $table->string('game_engine', 50)->default('unknown')->change();
        });
    }

    public function down(): void
    {
        // Remove defaults but keep the existing values
        Schema::table('games', function (Blueprint $table) {
            $table->string('status', 50)->default(null)->nullable(true)->change();
            $table->string('game_engine', 50)->default(null)->change();
        });
    }
};
