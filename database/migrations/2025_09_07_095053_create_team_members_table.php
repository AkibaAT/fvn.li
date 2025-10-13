<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('role', 50)->default('member');
            $table->unsignedBigInteger('custom_role_id')->nullable();
            $table->timestamp('joined_at')->useCurrent();
            $table->foreignId('invited_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('invitation_token')->nullable();
            $table->timestamp('invitation_accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'user_id']);
            $table->index('team_id');
            $table->index('user_id');
            $table->index('role');
            $table->index('invitation_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
