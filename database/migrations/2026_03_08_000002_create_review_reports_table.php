<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rating_id')->constrained('ratings')->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason'); // hate_speech, spam, harassment, spoilers, off_topic, other
            $table->text('details')->nullable();
            $table->string('status')->default('pending'); // pending, reviewed, dismissed, actioned
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->unique(['rating_id', 'reporter_id']);
            $table->index('status');
            $table->timestamp('discord_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_reports');
    }
};
