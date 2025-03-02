<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Tpetry\PostgresqlEnhanced\Schema\Blueprint;
use Tpetry\PostgresqlEnhanced\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create the unique_dialogue_texts table
        Schema::create('unique_dialogue_texts', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('text_hash', 32)
                ->unique()
                ->comment('MD5 hash of the text for quick lookups');
            $table->text('text_content');

            // Add tsvector column as a generated (stored) column
            $table->tsvector('search_vector')
                ->storedAs("to_tsvector('english', text_content)");

            // Create a GIN index on the search_vector column
            $table->index('search_vector')->using('gin');
        });

        // Create the version_dialogue_lines table
        Schema::create('version_dialogue_lines', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('game_version_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('character_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('iso_code', 10);
            $table->string('file_path', 255);
            $table->integer('line_number');

            // Add the text_id column from the first migration.
            // Since the table is being created now, we can simply define it in the desired order.
            $table->foreignId('text_id')
                ->nullable();

            $table->string('context', 255)->nullable();

            // Indexes for performance
            $table->index(['game_version_id', 'iso_code']);
            $table->index(['character_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('version_dialogue_lines');
        Schema::dropIfExists('unique_dialogue_texts');
    }
};
