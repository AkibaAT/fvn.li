<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create user_game_progress table
        Schema::create('user_game_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->foreignId('game_version_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('personal_notes')->nullable();
            $table->enum('status', ['reading', 'completed', 'plan_to_read', 'on_hold', 'dropped', 'custom'])->default('reading');
            $table->timestamps();

            // Ensure each user can only have one progress entry per game
            $table->unique(['user_id', 'game_id']);
        });

        // Remove progress-related columns from vn_list_entries
        Schema::table('vn_list_entries', function (Blueprint $table) {
            $table->dropColumn([
                'started_at',
                'completed_at',
                'progress',
                'notes',
                'game_version_id',
            ]);
        });
    }

    public function down(): void
    {
        // Add back columns to vn_list_entries
        Schema::table('vn_list_entries', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('progress')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('game_version_id')->nullable()->constrained()->nullOnDelete();
        });

        // Drop the user_game_progress table
        Schema::dropIfExists('user_game_progress');
    }
};
