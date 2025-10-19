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
            // Make itch_id nullable since Steam and other platform games won't have an itch.io ID
            $table->unsignedBigInteger('itch_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Revert to NOT NULL (this will fail if there are Steam games in the database)
            $table->unsignedBigInteger('itch_id')->nullable(false)->change();
        });
    }
};

