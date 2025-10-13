<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_member_id')->nullable()->constrained('team_members')->onDelete('cascade');
            $table->foreignId('team_role_id')->nullable()->constrained('team_roles')->onDelete('cascade');
            $table->string('permission', 100);
            $table->boolean('granted')->default(true);
            $table->timestamps();

            $table->index('team_member_id');
            $table->index('team_role_id');
            $table->index('permission');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_permissions');
    }
};
