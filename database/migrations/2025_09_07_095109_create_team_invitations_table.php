<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->string('email');
            $table->string('role', 50)->default('member');
            $table->unsignedBigInteger('custom_role_id')->nullable();
            $table->string('token')->unique();
            $table->foreignId('invited_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();

            $table->index('token');
            $table->index('email');
            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
    }
};
