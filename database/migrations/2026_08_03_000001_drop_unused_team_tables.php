<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('team_permissions');
        Schema::dropIfExists('team_invitations');
        Schema::dropIfExists('team_activity_logs');
        Schema::dropIfExists('team_games');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('team_roles');
        Schema::dropIfExists('teams');
    }

    public function down(): void
    {
        // The unused team-management feature has been removed; its data cannot be restored.
    }
};
