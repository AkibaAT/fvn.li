<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addition_requests', function (Blueprint $table) {
            $table->id();
            $table->string('itch_url')->index(); // The itch.io URL for the VN
            $table->string('normalized_url')->index(); // Normalized URL for deduplication
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->text('rejection_reason')->nullable(); // Admin reason for rejection
            $table->foreignId('game_id')->nullable()->constrained()->nullOnDelete(); // Link to created game if approved
            $table->timestamp('reviewed_at')->nullable(); // When admin reviewed the request
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); // Admin who reviewed
            $table->timestamps();

            // Index for efficient queries
            $table->index(['status', 'created_at']);
            $table->index(['normalized_url', 'status']);
        });

        // Pivot table to track which users requested which additions
        Schema::create('addition_request_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addition_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Prevent duplicate requests from same user for same addition
            $table->unique(['addition_request_id', 'user_id']);

            // Index for efficient user queries
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addition_request_users');
        Schema::dropIfExists('addition_requests');
    }
};
