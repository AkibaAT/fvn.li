<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_jams', function (Blueprint $table) {
            $table->dropColumn('theme');
            $table->dropColumn('optimized_logos');
            $table->dropColumn('logo_url');
        });
    }

    public function down(): void
    {
        Schema::table('game_jams', function (Blueprint $table) {
            $table->string('theme')->nullable();
            $table->json('optimized_logos')->nullable();
            $table->string('logo_url')->nullable();
        });
    }
};
