<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * The original ratings table was designed for fvn.li's event-based review system,
     * where each rating is tied to a specific event (hence the unique constraint on event_id).
     * 
     * With multi-platform support (Steam, etc.), external reviews don't have events.
     * This migration:
     * 1. Removes the unique constraint on event_id
     * 2. Makes event_id nullable (NULL for external reviews)
     */
    public function up(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            // Drop the unique constraint first
            $table->dropUnique('ratings_event_id_unique');
            
            // Make event_id nullable
            $table->unsignedBigInteger('event_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            // Make event_id NOT NULL again
            $table->unsignedBigInteger('event_id')->nullable(false)->change();
            
            // Restore the unique constraint
            $table->unique('event_id', 'ratings_event_id_unique');
        });
    }
};

