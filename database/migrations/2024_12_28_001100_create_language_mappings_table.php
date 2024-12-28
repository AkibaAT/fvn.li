<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('language_mappings', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('game_language_key', 50); // How it appears in the game (e.g. "francais", "anglais")
            $table->string('iso_code', 3);          // Standard language code (e.g. "fr-FR", "en-US")

            $table->unique(['game_language_key', 'iso_code']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('language_mappings');
    }
};
