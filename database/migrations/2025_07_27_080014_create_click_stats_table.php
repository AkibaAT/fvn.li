<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('click_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->string('type')->index(); // 'page_view' or 'custom_link'
            $table->string('link_id')->nullable()->index(); // For custom links, stores the link ID
            $table->string('session_id')->index(); // Laravel session ID for deduplication
            $table->string('ip_address')->nullable(); // For additional analytics
            $table->text('user_agent')->nullable(); // For analytics
            $table->string('referrer')->nullable(); // Where the click came from
            $table->timestamp('clicked_at')->index(); // When the click occurred
            $table->timestamps();

            // Indexes for efficient queries
            $table->index(['game_id', 'type', 'clicked_at']);
            $table->index(['game_id', 'link_id', 'clicked_at']);
            $table->index(['session_id', 'game_id', 'type', 'link_id']); // For deduplication
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('click_stats');
    }
};
