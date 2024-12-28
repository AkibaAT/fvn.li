<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn([
                'version',
                'version_published_at',
                'stats_blocks',
                'stats_menus',
                'stats_options',
                'stats_words',
                'languages',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('version', 20)->nullable();
            $table->timestamp('version_published_at')->nullable();
            $table->integer('stats_blocks')->nullable();
            $table->integer('stats_menus')->nullable();
            $table->integer('stats_options')->nullable();
            $table->integer('stats_words')->nullable();
            $table->string('languages')->nullable();
        });
    }
};
