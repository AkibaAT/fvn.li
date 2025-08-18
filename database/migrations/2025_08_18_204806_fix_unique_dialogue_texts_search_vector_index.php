<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the problematic btree index
        DB::statement('DROP INDEX IF EXISTS unique_dialogue_texts_search_vector_index');

        // Create a proper GIN index for tsvector full-text search
        DB::statement('CREATE INDEX unique_dialogue_texts_search_vector_gin_index ON unique_dialogue_texts USING gin (search_vector)');
    }

    public function down(): void
    {
        // Drop the GIN index
        DB::statement('DROP INDEX IF EXISTS unique_dialogue_texts_search_vector_gin_index');

        // Recreate the original btree index
        DB::statement('CREATE INDEX unique_dialogue_texts_search_vector_index ON unique_dialogue_texts USING btree (search_vector)');
    }
};
