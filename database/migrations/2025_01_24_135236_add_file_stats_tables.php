<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create categories table
        Schema::create('version_file_categories', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('game_version_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);  // images, audio, video, other
            $table->integer('total_count');
            $table->bigInteger('total_size');

            $table->unique(['game_version_id', 'category']);
            $table->index(['category']);
        });

        // Create file types table
        Schema::create('version_file_types', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('version_file_category_id')->constrained()->cascadeOnDelete();
            $table->string('extension', 10);  // .jpg, .mp3, etc
            $table->integer('count');
            $table->bigInteger('size');

            $table->unique(['version_file_category_id', 'extension']);
            $table->index(['extension']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('version_file_types');
        Schema::dropIfExists('version_file_categories');
    }
};
