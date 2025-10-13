<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->foreign('custom_role_id')->references('id')->on('team_roles')->onDelete('set null');
        });

        Schema::table('team_invitations', function (Blueprint $table) {
            $table->foreign('custom_role_id')->references('id')->on('team_roles')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropForeign(['custom_role_id']);
        });

        Schema::table('team_invitations', function (Blueprint $table) {
            $table->dropForeign(['custom_role_id']);
        });
    }
};
