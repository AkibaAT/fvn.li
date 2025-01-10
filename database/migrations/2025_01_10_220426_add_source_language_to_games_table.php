<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Add the source_language_id column
            $table->string('source_language_id', 3)->nullable();

            // Add foreign key constraint
            $table->foreign('source_language_id')
                ->references('id')
                ->on('iso_639_3_languages')
                ->onDelete('set null');
        });

        // Set default values based on existing data
        DB::statement("
            UPDATE games
            SET source_language_id = 'eng' WHERE is_visible is True;
        ");
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Remove foreign key constraint first
            $table->dropForeign(['source_language_id']);

            // Then remove the column
            $table->dropColumn('source_language_id');
        });
    }
};
