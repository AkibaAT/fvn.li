<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('discord_server_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discord_server_id')->constrained('discord_servers')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('discord_user_id')->unique();
            $table->string('discord_username');
            $table->boolean('is_admin')->default(false);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            // Indexes
            $table->index('discord_server_id');
            $table->index('user_id');
            $table->index('discord_user_id');
            $table->index('is_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discord_server_members');
    }
};

