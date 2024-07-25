<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Tpetry\PostgresqlEnhanced\Schema\Blueprint;
use Tpetry\PostgresqlEnhanced\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_versions', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->timestamp('published_at');
            $table->foreignId('game_id')->constrained();
            $table->string('version', 20);
            $table->string('devlog', 250)->nullable();
            $table->boolean('platform_windows')->default(false);
            $table->boolean('platform_linux')->default(false);
            $table->boolean('platform_mac')->default(false);
            $table->boolean('platform_android')->default(false);
            $table->boolean('platform_web')->default(false);
            $table->unsignedInteger('stats_blocks')->nullable();
            $table->unsignedInteger('stats_menus')->nullable();
            $table->unsignedInteger('stats_options')->nullable();
            $table->unsignedInteger('stats_words')->nullable();
            $table->float('rating')->nullable();
            $table->unsignedInteger('rating_count')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_versions');
    }
};
