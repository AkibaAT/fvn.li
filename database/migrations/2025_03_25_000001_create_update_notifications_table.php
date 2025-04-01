<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add receive_updates column to vn_list_entries table
        Schema::table('vn_list_entries', function (Blueprint $table) {
            $table->boolean('receive_updates')->default(false);
        });

        // Create notification_history table
        Schema::create('notification_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->foreignId('game_version_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['discord', 'telegram', 'email', 'browser'])->index();
            $table->boolean('success')->default(true);
            $table->text('meta_data')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'game_id', 'type']);
        });
    }

    public function down(): void
    {
        // Drop the notification_history table
        Schema::dropIfExists('notification_history');

        // Remove receive_updates column from vn_list_entries table
        Schema::table('vn_list_entries', function (Blueprint $table) {
            $table->dropColumn('receive_updates');
        });
    }
};
