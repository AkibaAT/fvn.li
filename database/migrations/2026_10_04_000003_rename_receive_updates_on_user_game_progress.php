<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_game_progress', function (Blueprint $table) {
            $table->renameColumn('receive_updates', 'is_receiving_updates');
        });
    }

    public function down(): void
    {
        Schema::table('user_game_progress', function (Blueprint $table) {
            $table->renameColumn('is_receiving_updates', 'receive_updates');
        });
    }
};
