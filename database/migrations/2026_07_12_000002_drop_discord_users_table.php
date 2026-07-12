<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy Discord bot subscriber list; unread since the per-user
        // notification rework and superseded by social_accounts + user
        // notification preferences.
        Schema::dropIfExists('discord_users');
    }

    public function down(): void
    {
        Schema::create('discord_users', function (Blueprint $table) {
            $table->id();
            $table->string('discord_id', 100)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }
};
