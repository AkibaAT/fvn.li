<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_tag', function (Blueprint $table) {
            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        Schema::table('game_tag', function (Blueprint $table) {
            $table->dropIndex(['tag_id']);
        });
    }
};
