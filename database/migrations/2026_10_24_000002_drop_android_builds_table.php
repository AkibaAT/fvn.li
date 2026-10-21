<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('android_builds');
    }

    public function down(): void
    {
        Schema::create('android_builds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_version_id')->constrained()->cascadeOnDelete();
            $table->uuid('build_id')->unique();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('build_path')->nullable();
            $table->string('keystore_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['game_id', 'game_version_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }
};
