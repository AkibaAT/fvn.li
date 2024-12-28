<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('version_language_stats', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('game_version_id')->constrained('game_versions')->onDelete('cascade');
            $table->string('iso_code', 10);
            $table->integer('blocks')->nullable();
            $table->integer('words')->nullable();
            $table->integer('menus')->nullable();
            $table->integer('options')->nullable();

            $table->unique(['game_version_id', 'iso_code']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('version_language_stats');
    }
};
