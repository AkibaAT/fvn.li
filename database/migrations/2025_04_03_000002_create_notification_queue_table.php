<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->foreignId('game_version_id')->constrained()->onDelete('cascade');
            $table->enum('channel', ['browser', 'discord', 'email']);
            $table->string('status', 20)->default('pending'); // pending, processing, sent, failed
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('payload')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            // Indexes for faster querying
            $table->index(['user_id', 'channel', 'status']);
            $table->index(['channel', 'status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_queue');
    }
};
