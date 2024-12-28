<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('version_character_stats', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('game_version_id')->constrained('game_versions')->onDelete('cascade');
            $table->string('iso_code', 10);
            $table->string('character_id', 50);      // The internal ID used in the game (e.g. "e", "m", "narrator")
            $table->string('display_name', 100);     // The display name for this character
            $table->integer('blocks')->default(0);
            $table->integer('words')->default(0);

            $table->unique(['game_version_id', 'iso_code', 'character_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('version_character_stats');
    }
};
