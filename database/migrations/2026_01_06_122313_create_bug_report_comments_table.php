<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bug_report_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bug_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->boolean('is_from_admin')->default(false);
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['bug_report_id', 'created_at']);
            $table->index(['bug_report_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bug_report_comments');
    }
};
