<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vn_list_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vn_list_id')->constrained()->onDelete('cascade');
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->foreignId('game_version_id')->nullable()->constrained('game_versions')->onDelete('set null');
            $table->integer('progress')->default(0); // Percentage or chapter count
            $table->text('notes')->nullable();
            $table->integer('rating')->nullable(); // User's personal rating (1-10)
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Each game can only be in a specific list once
            $table->unique(['vn_list_id', 'game_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vn_list_entries');
    }
};
