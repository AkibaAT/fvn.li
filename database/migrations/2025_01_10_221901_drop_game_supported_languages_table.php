<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('game_supported_languages');
    }

    public function down(): void
    {
        Schema::create('game_supported_languages', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->string('iso_code', 3);

            $table->unique(['game_id', 'iso_code']);
        });
    }
};
