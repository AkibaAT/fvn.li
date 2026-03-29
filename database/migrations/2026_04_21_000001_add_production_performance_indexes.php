<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('games', 'content_type', 'games_content_type_index');
        $this->addIndexIfMissing('games', ['is_visible', 'content_type'], 'games_visible_content_type_index');
        $this->addIndexIfMissing('games', ['is_visible', 'is_nsfw'], 'games_visible_nsfw_index');
        $this->addIndexIfMissing('social_accounts', 'user_id', 'social_accounts_user_id_index');
        $this->addIndexIfMissing('user_game_progress', 'user_id', 'user_game_progress_user_id_index');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('user_game_progress', 'user_game_progress_user_id_index');
        $this->dropIndexIfExists('social_accounts', 'social_accounts_user_id_index');
        $this->dropIndexIfExists('games', 'games_visible_nsfw_index');
        $this->dropIndexIfExists('games', 'games_visible_content_type_index');
        $this->dropIndexIfExists('games', 'games_content_type_index');
    }

    private function addIndexIfMissing(string $tableName, string|array $columns, string $indexName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $columnList = is_array($columns) ? $columns : [$columns];

        if (Schema::hasIndex($tableName, $indexName) || Schema::hasIndex($tableName, $columnList)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName) {
            $table->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName) {
            $table->dropIndex($indexName);
        });
    }
};
