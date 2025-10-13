<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            if (Schema::hasColumn('team_invitations', 'email')) {
                $table->dropIndex(['email']);
                $table->dropColumn('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->string('email')->nullable();
            $table->index('email');
        });
    }
};
