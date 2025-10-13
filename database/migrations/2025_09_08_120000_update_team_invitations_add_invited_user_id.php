<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            // Add invited_user_id for user-ID based invitations
            $table->foreignId('invited_user_id')->nullable()->after('team_id')->constrained('users')->onDelete('cascade');
            $table->index('invited_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            // Revert changes (note: dropping foreign before dropping column)
            $table->dropForeign(['invited_user_id']);
            $table->dropIndex(['team_invitations_invited_user_id_index']);
            $table->dropColumn('invited_user_id');
        });
    }
};
