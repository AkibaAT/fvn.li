<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Tpetry\PostgresqlEnhanced\Schema\Blueprint;
use Tpetry\PostgresqlEnhanced\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createExtensionIfNotExists('citext');
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->timestamp('initially_published_at')->nullable();
            $table->timestamp('version_published_at')->nullable();
            $table->unsignedInteger('game_id');
            $table->caseInsensitiveText('name');
            $table->string('status', 50)->nullable();
            $table->boolean('visible')->default(false);
            $table->boolean('nsfw')->default(false);
            $table->text('description')->nullable();
            $table->string('url');
            $table->string('thumb_url')->nullable();
            $table->string('version')->nullable();
            $table->string('tags')->nullable();
            $table->float('rating')->nullable();
            $table->unsignedInteger('rating_count')->nullable();
            $table->string('devlog', 250)->nullable();
            $table->string('languages')->nullable();
            $table->boolean('platform_windows')->default(false);
            $table->boolean('platform_linux')->default(false);
            $table->boolean('platform_mac')->default(false);
            $table->boolean('platform_android')->default(false);
            $table->boolean('platform_web')->default(false);
            $table->unsignedInteger('stats_blocks')->nullable();
            $table->unsignedInteger('stats_menus')->nullable();
            $table->unsignedInteger('stats_options')->nullable();
            $table->unsignedInteger('stats_words')->nullable();
            $table->string('game_engine', 50);
            $table->text('error')->nullable();
            $table->unique(['game_id']);
            $table->index(['visible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
