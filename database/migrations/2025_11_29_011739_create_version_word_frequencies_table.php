<?php

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
        Schema::create('version_word_frequencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->onDelete('cascade');
            $table->string('iso_code', 3);
            $table->json('word_data'); // Stores array of {text, value}
            $table->timestamp('calculated_at');
            $table->timestamps();

            // Unique constraint: one cached result per version+language combination
            $table->unique(['game_version_id', 'iso_code']);

            // Foreign key to languages table
            $table->foreign('iso_code')->references('id')->on('iso_639_3_languages')->onDelete('cascade');

            // Index for lookups
            $table->index('calculated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('version_word_frequencies');
    }
};
