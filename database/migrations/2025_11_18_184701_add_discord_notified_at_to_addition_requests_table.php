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
        Schema::table('addition_requests', function (Blueprint $table) {
            $table->timestamp('discord_notified_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addition_requests', function (Blueprint $table) {
            $table->dropColumn('discord_notified_at');
        });
    }
};
